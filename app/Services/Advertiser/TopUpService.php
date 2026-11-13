<?php

namespace App\Services\Advertiser;

use App\Enums\TopUpProvider;
use App\Enums\TopUpStatus;
use App\Enums\TransactionAction;
use App\Models\TopUp;
use App\Models\User;
use App\Services\Settings;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Creates and completes advertiser wallet top ups.
 */
class TopUpService
{
    private const SEPAY_VALID_HOURS = 24;

    /**
     * Never look further back than this when matching bank transfers to open top ups.
     */
    private const SEPAY_LOOKBACK_DAYS = 30;

    public function __construct(
        private readonly WalletService $wallet,
        private readonly SepayClient $sepay,
    ) {}

    /**
     * Smallest bank transfer in VND that reaches the minimum top up at the current rate.
     */
    public function minimumVnd(): int
    {
        return (int) ceil(Settings::int('advertiser_min_topup_micro') * $this->vndPerUsd() / 1_000_000);
    }

    /**
     * Start a bank transfer top up. The exchange rate is locked now, so the credited amount
     * is the one the advertiser was shown even if the rate changes before the payment arrives.
     */
    public function createSepay(User $user, int $vnd): TopUp
    {
        $rate = $this->vndPerUsd();
        $micro = intdiv($vnd * 1_000_000, $rate);

        if ($micro < Settings::int('advertiser_min_topup_micro')) {
            throw new InvalidArgumentException('The amount is below the minimum top up.');
        }

        return TopUp::query()->create([
            'user_id' => $user->getKey(),
            'provider' => TopUpProvider::Sepay,
            'reference' => 'LC'.$user->getKey().strtoupper(Str::random(6)),
            'amount_micro' => $micro,
            'original_amount' => $vnd,
            'original_currency' => 'VND',
            'exchange_rate' => $rate,
            'expires_at' => now()->addHours(self::SEPAY_VALID_HOURS),
        ]);
    }

    /**
     * Match the incoming bank transfers to open top ups. Returns the number of top ups credited.
     *
     * A transfer that matches a pending top up in time and amount credits it. A transfer that names
     * a top up but has the wrong amount, or arrives after the top up expired, is marked "mismatch"
     * for an admin to resolve. Every other transfer is ignored. A top up that has left the open
     * states is never matched again, so running this twice never credits twice.
     */
    public function syncSepay(): int
    {
        TopUp::query()
            ->where('provider', TopUpProvider::Sepay)
            ->where('status', TopUpStatus::Pending)
            ->where('expires_at', '<', now())
            ->update(['status' => TopUpStatus::Failed]);

        $transactions = $this->sepay->recentIncoming();

        if ($transactions === []) {
            return 0;
        }

        $open = TopUp::query()
            ->where('provider', TopUpProvider::Sepay)
            ->whereIn('status', [TopUpStatus::Pending, TopUpStatus::Failed])
            ->where('created_at', '>=', now()->subDays(self::SEPAY_LOOKBACK_DAYS))
            ->get();

        $credited = 0;

        foreach ($transactions as $transaction) {
            $topUp = $open->first(fn (TopUp $candidate): bool => stripos($transaction['content'], $candidate->reference) !== false);

            if ($topUp === null) {
                continue;
            }

            $topUp->refresh();

            if ($topUp->status !== TopUpStatus::Pending && $topUp->status !== TopUpStatus::Failed) {
                continue;
            }

            $raw = ['sepay_id' => $transaction['id'], 'transaction' => $transaction['raw']];

            if ($topUp->status === TopUpStatus::Pending && $transaction['amount'] === $topUp->original_amount) {
                $this->complete($topUp, $raw);
                $credited++;
            } else {
                $topUp->update(['status' => TopUpStatus::Mismatch, 'raw' => $raw]);
            }
        }

        return $credited;
    }

    /**
     * Credit a pending top up to the wallet. Does nothing when it is no longer pending.
     *
     * @param  array<string, mixed>  $raw
     */
    public function complete(TopUp $topUp, array $raw = []): void
    {
        DB::transaction(function () use ($topUp, $raw): void {
            $locked = TopUp::query()->whereKey($topUp->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== TopUpStatus::Pending) {
                return;
            }

            $this->wallet->credit(
                User::query()->findOrFail($locked->user_id),
                $locked->amount_micro,
                TransactionAction::Topup,
                'topup:'.$locked->getKey(),
                $locked->provider->label().' top up',
            );

            $locked->update([
                'status' => TopUpStatus::Completed,
                'completed_at' => now(),
                'raw' => $raw === [] ? $locked->raw : $raw,
            ]);
        });

        $topUp->refresh();
    }

    private function vndPerUsd(): int
    {
        return max(1, Settings::int('vnd_per_usd'));
    }
}

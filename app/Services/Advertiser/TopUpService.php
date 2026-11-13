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
use RuntimeException;

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
        private readonly PaypalClient $paypal,
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
     * Create a PayPal order and a pending top up for it. Returns the top up and the url where the buyer pays.
     *
     * @return array{top_up: TopUp, approve_url: string}
     *
     * @throws RuntimeException when PayPal cannot create the order
     */
    public function createPaypal(User $user, int $usdMicro): array
    {
        if ($usdMicro < Settings::int('advertiser_min_topup_micro')) {
            throw new InvalidArgumentException('The amount is below the minimum top up.');
        }

        $order = $this->paypal->createOrder(
            $usdMicro,
            route('advertiser.topups.paypal.return'),
            route('advertiser.topups.paypal.cancel'),
        );

        $topUp = TopUp::query()->create([
            'user_id' => $user->getKey(),
            'provider' => TopUpProvider::Paypal,
            'reference' => $order['id'],
            'amount_micro' => $usdMicro,
            'original_amount' => $usdMicro,
            'original_currency' => 'USD',
        ]);

        return ['top_up' => $topUp, 'approve_url' => $order['approve_url']];
    }

    /**
     * Capture the approved PayPal order and credit the wallet when PayPal confirms the full amount.
     * An answer that is not a completed capture of exactly the ordered amount marks the top up failed.
     *
     * @throws RuntimeException when PayPal cannot be reached; the top up stays pending so the buyer can retry
     */
    public function capturePaypal(TopUp $topUp): TopUpStatus
    {
        if ($topUp->status !== TopUpStatus::Pending) {
            return $topUp->status;
        }

        $answer = $this->paypal->captureOrder($topUp->reference);
        $capture = $answer['purchase_units'][0]['payments']['captures'][0] ?? [];

        $isPaid = ($answer['status'] ?? null) === 'COMPLETED'
            && ($capture['status'] ?? null) === 'COMPLETED'
            && ($capture['amount']['currency_code'] ?? null) === 'USD'
            && (int) round((float) ($capture['amount']['value'] ?? 0) * 1_000_000) === $topUp->amount_micro;

        if (! $isPaid) {
            $topUp->update(['status' => TopUpStatus::Failed, 'raw' => $answer]);

            return TopUpStatus::Failed;
        }

        $this->complete($topUp, $answer);

        return $topUp->status;
    }

    /**
     * Mark a pending top up as abandoned.
     */
    public function cancel(TopUp $topUp): void
    {
        if ($topUp->status === TopUpStatus::Pending) {
            $topUp->update(['status' => TopUpStatus::Failed]);
        }
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
     * Credit a pending top up to the wallet. Does nothing when it is no longer pending. An admin who checked
     * the bank statement can also credit a "mismatch" top up with $allowMismatch.
     *
     * @param  array<string, mixed>  $raw
     */
    public function complete(TopUp $topUp, array $raw = [], bool $allowMismatch = false): void
    {
        DB::transaction(function () use ($topUp, $raw, $allowMismatch): void {
            $locked = TopUp::query()->whereKey($topUp->getKey())->lockForUpdate()->firstOrFail();

            $creditable = $locked->status === TopUpStatus::Pending
                || ($allowMismatch && $locked->status === TopUpStatus::Mismatch);

            if (! $creditable) {
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

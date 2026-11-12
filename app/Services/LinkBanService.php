<?php

namespace App\Services;

use App\Enums\LinkStatus;
use App\Enums\TransactionAction;
use App\Enums\ViewStatus;
use App\Models\DailyStat;
use App\Models\FraudFlag;
use App\Models\Link;
use App\Models\User;
use App\Models\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LinkBanService
{
    public function __construct(private readonly WalletService $wallet) {}

    /**
     * Ban a link. Everything happens in one database transaction:
     * the link is marked banned, its pending views are cancelled, and the money already paid for
     * its released views is taken back from the owner (earnings adjustment) and from the owner's
     * referrer (referral reversal). A reversal never takes more than the current balance, because
     * the money may already have been withdrawn. Banning a banned link does nothing, so nothing
     * is ever reversed twice.
     */
    public function ban(Link $link, string $reason): void
    {
        DB::transaction(function () use ($link, $reason): void {
            $locked = Link::query()->whereKey($link->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === LinkStatus::Banned) {
                return;
            }

            $locked->update(['status' => LinkStatus::Banned]);

            $pending = View::query()
                ->where('link_id', $locked->getKey())
                ->where('status', ViewStatus::Pending)
                ->lockForUpdate()
                ->get(['id', 'user_id', 'earn_micro', 'created_at', 'aggregated_at']);

            if ($pending->isNotEmpty()) {
                View::query()->whereKey($pending->modelKeys())->update(['status' => ViewStatus::Cancelled]);
            }

            $released = View::query()
                ->where('link_id', $locked->getKey())
                ->where('status', ViewStatus::Released)
                ->lockForUpdate()
                ->get(['id', 'user_id', 'earn_micro', 'created_at', 'aggregated_at']);

            $this->rollBackStats($pending->concat($released));
            $this->reverseReleasedEarnings($locked, (int) $released->sum('earn_micro'), $reason);

            // Open fraud flags of this link are handled by the ban.
            FraudFlag::query()
                ->where('link_id', $locked->getKey())
                ->whereNull('resolved_at')
                ->update(['resolved_at' => now()]);
        });

        $link->refresh();
    }

    /**
     * Take back the released earnings from the link owner and the commission from the owner's referrer.
     */
    private function reverseReleasedEarnings(Link $link, int $releasedMicro, string $reason): void
    {
        if ($releasedMicro <= 0) {
            return;
        }

        $owner = User::query()->find($link->user_id);

        if ($owner === null) {
            return;
        }

        $reference = 'ban:'.$link->getKey();
        $description = Str::limit('Link '.$link->alias.' banned: '.$reason, 250, '');

        $this->debitUpToBalance($owner, $releasedMicro, TransactionAction::EarningsAdjustment, $reference, $description);

        $commission = intdiv($releasedMicro * Settings::int('referral_percent'), 100);

        if ($commission <= 0 || $owner->referred_by === null) {
            return;
        }

        $referrer = User::query()->find($owner->referred_by);

        if ($referrer === null) {
            return;
        }

        $reversed = $this->debitUpToBalance($referrer, $commission, TransactionAction::ReferralReversal, $reference, $description);

        if ($reversed > 0) {
            $this->decrementStat($referrer->id, now()->utc()->toDateString(), 0, 0, $reversed);
        }
    }

    /**
     * Debit the wanted amount, or whatever balance is left when it is lower. Returns the amount taken.
     */
    private function debitUpToBalance(User $user, int $micro, TransactionAction $action, string $reference, string $description): int
    {
        $current = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
        $amount = min($micro, $current->balance_micro);

        if ($amount <= 0) {
            return 0;
        }

        $this->wallet->debit($current, $amount, $action, $reference, $description);

        return $amount;
    }

    /**
     * Remove the views and earnings of cancelled or reversed views from the daily stats they were folded into.
     *
     * @param  Collection<int, View>|\Illuminate\Support\Collection<int, View>  $views
     */
    private function rollBackStats(iterable $views): void
    {
        $groups = collect($views)
            ->filter(fn (View $view): bool => $view->aggregated_at !== null)
            ->groupBy(fn (View $view): string => $view->user_id.'|'.$view->created_at->copy()->utc()->toDateString());

        foreach ($groups as $key => $group) {
            [$userId, $date] = explode('|', (string) $key);

            $this->decrementStat((int) $userId, $date, $group->count(), (int) $group->sum('earn_micro'), 0);
        }
    }

    private function decrementStat(int $userId, string $date, int $views, int $earnMicro, int $refEarnMicro): void
    {
        $stat = DailyStat::query()
            ->where('user_id', $userId)
            ->whereDate('stat_date', $date)
            ->lockForUpdate()
            ->first();

        if ($stat === null) {
            return;
        }

        $stat->update([
            'views' => max(0, $stat->views - $views),
            'earn_micro' => max(0, $stat->earn_micro - $earnMicro),
            'ref_earn_micro' => max(0, $stat->ref_earn_micro - $refEarnMicro),
        ]);
    }
}

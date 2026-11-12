<?php

namespace App\Services\Views;

use App\Enums\TransactionAction;
use App\Enums\ViewStatus;
use App\Models\DailyStat;
use App\Models\User;
use App\Models\View;
use App\Services\Settings;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;

class EarningsReleaser
{
    public function __construct(private readonly WalletService $wallet) {}

    /**
     * Release every pending view whose review period has ended, crediting each
     * user once for the total and paying the referrer a commission on top.
     * Returns the number of views released. Re-running never pays twice, because
     * only still-pending views are selected, locked and flipped in the same
     * database transaction as the credit.
     */
    public function release(): int
    {
        $released = 0;

        $userIds = View::query()
            ->where('status', ViewStatus::Pending)
            ->where('matures_at', '<=', now())
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $released += $this->releaseForUser((int) $userId);
        }

        return $released;
    }

    private function releaseForUser(int $userId): int
    {
        return DB::transaction(function () use ($userId): int {
            $views = View::query()
                ->where('user_id', $userId)
                ->where('status', ViewStatus::Pending)
                ->where('matures_at', '<=', now())
                ->lockForUpdate()
                ->get(['id', 'earn_micro']);

            if ($views->isEmpty()) {
                return 0;
            }

            $total = (int) $views->sum('earn_micro');

            View::query()
                ->whereKey($views->modelKeys())
                ->where('status', ViewStatus::Pending)
                ->update(['status' => ViewStatus::Released]);

            if ($total > 0) {
                $user = User::query()->findOrFail($userId);
                $reference = 'release:'.$userId.':'.now()->utc()->format('YmdHis');

                $this->wallet->credit($user, $total, TransactionAction::ViewEarnings, $reference, 'Released view earnings');
                $this->payReferrer($user, $total, $reference);
            }

            return $views->count();
        });
    }

    /**
     * Pay the referrer a percentage of the released total as a separate
     * transaction. The referred user's own earnings are not reduced.
     */
    private function payReferrer(User $user, int $total, string $reference): void
    {
        if ($user->referred_by === null) {
            return;
        }

        $commission = intdiv($total * Settings::int('referral_percent'), 100);

        if ($commission <= 0) {
            return;
        }

        $referrer = User::query()->find($user->referred_by);

        if ($referrer === null) {
            return;
        }

        $this->wallet->credit($referrer, $commission, TransactionAction::ReferralCommission, (string) $user->id, 'Referral commission for release '.$reference);

        DailyStat::query()
            ->firstOrCreate(['user_id' => $referrer->id, 'stat_date' => now()->utc()->toDateString()])
            ->incrementEach(['ref_earn_micro' => $commission]);
    }
}

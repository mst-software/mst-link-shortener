<?php

namespace App\Services;

use App\Enums\AdminAction;
use App\Enums\FootprintSignal;
use App\Enums\TransactionAction;
use App\Models\DailyStat;
use App\Models\FraudFlag;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pays referral commissions and guards them against self-referral with fake accounts.
 *
 * When the referrer and the referred user signed up from the same IP address with the same browser, a
 * "multi_account_referral" fraud flag is raised once per pair for admin review. Payouts are never changed
 * silently: the commission is paid as usual unless the "referral_hold_on_suspicion" setting is on, in which
 * case it is kept on the open flag (held_commission_micro) until an admin pays it out or marks the flag handled.
 */
class ReferralCommissionService
{
    public const FLAG_REASON = 'multi_account_referral';

    public function __construct(
        private readonly WalletService $wallet,
        private readonly MultiAccountDetector $detector,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Credit a commission to the referrer and add it to today's referral earnings.
     * Must run inside the caller's database transaction.
     */
    public function pay(User $referrer, int $commissionMicro, int $referredUserId, string $description): Transaction
    {
        $transaction = $this->wallet->credit($referrer, $commissionMicro, TransactionAction::ReferralCommission, (string) $referredUserId, $description);

        $statDate = now()->utc()->toDateString();

        DailyStat::query()->firstOrCreate(['user_id' => $referrer->id, 'stat_date' => $statDate]);

        DailyStat::query()
            ->where('user_id', $referrer->id)
            ->whereDate('stat_date', $statDate)
            ->incrementEach(['ref_earn_micro' => $commissionMicro]);

        return $transaction;
    }

    /**
     * Raise the review flag for a suspicious pair (once) and, when holding is on and the flag is still open,
     * add the commission to the flag instead of paying it. Returns true when the commission was held.
     * Must run inside the caller's database transaction.
     */
    public function holdIfSuspicious(User $referrer, User $referred, int $commissionMicro): bool
    {
        $flag = $this->flagFor($referrer, $referred) ?? $this->raiseFlag($referrer, $referred);

        if ($flag === null || $flag->resolved_at !== null || ! Settings::bool('referral_hold_on_suspicion')) {
            return false;
        }

        $flag->update(['details' => [
            ...(array) $flag->details,
            'held_commission_micro' => (int) ($flag->details['held_commission_micro'] ?? 0) + $commissionMicro,
        ]]);

        return true;
    }

    /**
     * Take back commission for reversed earnings from what is still held on the pair's open flag first,
     * since held commission was never credited. Returns the part that was held (and is now removed from
     * the flag); the caller debits only the rest from the referrer. Must run inside a database transaction.
     */
    public function reverseHeld(User $referrer, User $referred, int $commissionMicro): int
    {
        $flag = $this->flagFor($referrer, $referred);
        $held = (int) ($flag?->details['held_commission_micro'] ?? 0);

        if ($flag === null || $flag->resolved_at !== null || $held <= 0 || $commissionMicro <= 0) {
            return 0;
        }

        $fromHeld = min($held, $commissionMicro);

        $flag->update(['details' => [
            ...(array) $flag->details,
            'held_commission_micro' => $held - $fromHeld,
            'reversed_held_micro' => (int) ($flag->details['reversed_held_micro'] ?? 0) + $fromHeld,
        ]]);

        return $fromHeld;
    }

    /**
     * Pay the commission held on a flag to the referrer and mark the flag handled.
     *
     * @throws ValidationException
     */
    public function payHeld(User $admin, FraudFlag $flag): int
    {
        return DB::transaction(function () use ($admin, $flag): int {
            $locked = FraudFlag::query()->whereKey($flag->getKey())->lockForUpdate()->firstOrFail();
            $held = (int) ($locked->details['held_commission_micro'] ?? 0);
            $referrer = $locked->user_id === null ? null : User::query()->find($locked->user_id);

            if ($locked->reason !== self::FLAG_REASON || $locked->resolved_at !== null || $held <= 0 || $referrer === null) {
                throw ValidationException::withMessages(['flag' => 'This flag has no held commission to pay.']);
            }

            $referredUserId = (int) ($locked->details['referred_user_id'] ?? 0);

            $this->pay($referrer, $held, $referredUserId, 'Held referral commission paid after review');

            $locked->update([
                'details' => [...(array) $locked->details, 'held_commission_micro' => 0, 'paid_commission_micro' => $held],
                'resolved_at' => now(),
            ]);

            $this->audit->record($admin, AdminAction::HeldCommissionPaid, $referrer, 'Fraud flag #'.$locked->getKey(),
                ['held_commission_micro' => $held],
                ['paid_commission_micro' => $held],
            );

            return $held;
        });
    }

    private function flagFor(User $referrer, User $referred): ?FraudFlag
    {
        return FraudFlag::query()
            ->where('reason', self::FLAG_REASON)
            ->where('user_id', $referrer->getKey())
            ->where('details->referred_user_id', $referred->getKey())
            ->lockForUpdate()
            ->first();
    }

    /**
     * Create the review flag when the two accounts share the sign-up IP and the browser, or return null.
     */
    private function raiseFlag(User $referrer, User $referred): ?FraudFlag
    {
        $related = $this->detector->compare($referrer, $referred);

        if ($related === null || ! $related->hasSignal(FootprintSignal::SameSignupIp) || ! $related->hasSignal(FootprintSignal::SameDevice)) {
            return null;
        }

        return FraudFlag::query()->create([
            'user_id' => $referrer->getKey(),
            'link_id' => null,
            'reason' => self::FLAG_REASON,
            'details' => [
                'referrer_id' => $referrer->getKey(),
                'referred_user_id' => $referred->getKey(),
                'signals' => array_map(fn (FootprintSignal $signal): string => $signal->value, $related->signals),
                'score' => $related->score(),
                'shared_ips' => $related->sharedIps,
                'held_commission_micro' => 0,
            ],
        ]);
    }
}

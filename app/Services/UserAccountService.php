<?php

namespace App\Services;

use App\Enums\AdminAction;
use App\Enums\LinkStatus;
use App\Enums\TransactionAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Link;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Account actions administrators take on users from the admin area. Every action is written to the audit log.
 */
class UserAccountService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly WalletService $wallet,
        private readonly LinkBanService $linkBans,
    ) {}

    /**
     * Ban a user: they can no longer sign in, their remember-me token is replaced and their stored
     * sessions are removed. When asked, all their links that are not banned yet are banned too,
     * which cancels pending views and takes back released earnings (see LinkBanService).
     *
     * @throws ValidationException
     */
    public function ban(User $admin, User $user, string $reason, bool $banLinks = false): void
    {
        $this->ensureNotSelf($admin, $user, 'You cannot ban your own account.');

        DB::transaction(function () use ($admin, $user, $reason, $banLinks): void {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isActive()) {
                throw ValidationException::withMessages(['user' => 'This user is already banned.']);
            }

            if ($locked->isAdmin()) {
                $this->ensureAnotherActiveAdminRemains($locked, 'The last active admin cannot be banned.');
            }

            $locked->forceFill(['status' => UserStatus::Banned])->setRememberToken(Str::random(60));
            $locked->save();

            $bannedLinks = 0;

            if ($banLinks) {
                $links = Link::query()
                    ->where('user_id', $locked->getKey())
                    ->where('status', '!=', LinkStatus::Banned)
                    ->get();

                foreach ($links as $link) {
                    $this->linkBans->ban($link, 'Owner banned: '.$reason);
                }

                $bannedLinks = $links->count();
            }

            $this->audit->record($admin, AdminAction::UserBanned, $locked, $reason,
                ['status' => UserStatus::Active->value],
                ['status' => UserStatus::Banned->value, 'links_banned' => $bannedLinks],
            );
        });

        $this->removeStoredSessions($user);

        $user->refresh();
    }

    /**
     * Let a banned user sign in again. Links banned together with the account stay banned.
     *
     * @throws ValidationException
     */
    public function unban(User $admin, User $user, ?string $reason = null): void
    {
        DB::transaction(function () use ($admin, $user, $reason): void {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isActive()) {
                throw ValidationException::withMessages(['user' => 'This user is not banned.']);
            }

            $locked->forceFill(['status' => UserStatus::Active])->save();

            $this->audit->record($admin, AdminAction::UserUnbanned, $locked, $reason,
                ['status' => UserStatus::Banned->value],
                ['status' => UserStatus::Active->value],
            );
        });

        $user->refresh();
    }

    /**
     * Switch a user between member and admin. Advertiser accounts cannot be changed here.
     *
     * @throws ValidationException
     */
    public function changeRole(User $admin, User $user, UserRole $role, ?string $reason = null): void
    {
        if (! in_array($role, [UserRole::Member, UserRole::Admin], true)) {
            throw ValidationException::withMessages(['role' => 'A user can only be made a member or an admin.']);
        }

        $this->ensureNotSelf($admin, $user, 'You cannot change your own role.');

        DB::transaction(function () use ($admin, $user, $role, $reason): void {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $oldRole = $locked->role;

            if ($oldRole === UserRole::Advertiser) {
                throw ValidationException::withMessages(['role' => 'Advertiser accounts cannot change role.']);
            }

            if ($oldRole === $role) {
                throw ValidationException::withMessages(['role' => 'The user already has this role.']);
            }

            if ($oldRole === UserRole::Admin) {
                $this->ensureAnotherActiveAdminRemains($locked, 'The last active admin cannot be demoted.');
            }

            $locked->forceFill(['role' => $role])->save();

            $this->audit->record($admin, AdminAction::RoleChanged, $locked, $reason,
                ['role' => $oldRole->value],
                ['role' => $role->value],
            );
        });

        $user->refresh();
    }

    /**
     * Credit or debit the available balance through the wallet ledger. A debit never takes the balance below zero.
     *
     * @throws ValidationException
     */
    public function adjustBalance(User $admin, User $user, bool $isCredit, int $micro, string $reason): Transaction
    {
        $reference = 'admin:'.$admin->getKey();
        $description = Str::limit('Admin adjustment: '.$reason, 250, '');

        try {
            return DB::transaction(function () use ($admin, $user, $isCredit, $micro, $reason, $reference, $description): Transaction {
                $transaction = $isCredit
                    ? $this->wallet->credit($user, $micro, TransactionAction::AdminAdjustment, $reference, $description)
                    : $this->wallet->debit($user, $micro, TransactionAction::AdminAdjustment, $reference, $description);

                $this->audit->record($admin, AdminAction::BalanceAdjusted, $user, $reason,
                    ['balance_micro' => $transaction->balance_before_micro],
                    [
                        'balance_micro' => $transaction->balance_after_micro,
                        'amount' => ($isCredit ? '+' : '-').Money::format($micro, 6),
                        'transaction_id' => $transaction->getKey(),
                    ],
                );

                return $transaction;
            });
        } catch (InsufficientBalanceException) {
            throw ValidationException::withMessages([
                'amount' => 'The debit is larger than the available balance ('.Money::format($user->refresh()->balance_micro, 6).').',
            ]);
        }
    }

    /**
     * Mark the user's email address as verified without the verification mail.
     *
     * @throws ValidationException
     */
    public function markEmailVerified(User $admin, User $user, ?string $reason = null): void
    {
        if ($user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['user' => 'This email address is already verified.']);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        $this->audit->record($admin, AdminAction::EmailVerified, $user, $reason,
            ['email_verified_at' => null],
            ['email_verified_at' => $user->email_verified_at?->toDateTimeString()],
        );
    }

    /**
     * Send the user a new email verification link.
     *
     * @throws ValidationException
     */
    public function sendVerificationEmail(User $admin, User $user, ?string $reason = null): void
    {
        if ($user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['user' => 'This email address is already verified.']);
        }

        $user->sendEmailVerificationNotification();

        $this->audit->record($admin, AdminAction::VerificationEmailSent, $user, $reason, [], ['email' => $user->email]);
    }

    /**
     * Send the user a password reset link, using the same broker as the forgot password page.
     *
     * @throws ValidationException
     */
    public function sendPasswordResetLink(User $admin, User $user, ?string $reason = null): void
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['user' => __($status)]);
        }

        $this->audit->record($admin, AdminAction::PasswordResetSent, $user, $reason, [], ['email' => $user->email]);
    }

    /**
     * @throws ValidationException
     */
    private function ensureNotSelf(User $admin, User $user, string $message): void
    {
        if ($admin->is($user)) {
            throw ValidationException::withMessages(['user' => $message]);
        }
    }

    /**
     * Refuse the change when the given admin is the only active admin left. The admin rows are
     * locked so two admins demoting each other at the same time cannot both succeed.
     *
     * @throws ValidationException
     */
    private function ensureAnotherActiveAdminRemains(User $admin, string $message): void
    {
        $otherActiveAdmins = User::query()
            ->where('role', UserRole::Admin)
            ->where('status', UserStatus::Active)
            ->whereKeyNot($admin->getKey())
            ->lockForUpdate()
            ->pluck('id');

        if ($otherActiveAdmins->isEmpty()) {
            throw ValidationException::withMessages(['user' => $message]);
        }
    }

    /**
     * Remove the user's stored sessions so a banned user is signed out everywhere at once.
     * Only the database session driver keeps sessions per user; with other drivers the
     * "active" middleware signs the user out on their next request instead.
     */
    private function removeStoredSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->delete();
    }
}

<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Turns authenticator-app two-factor authentication on and off for a user and checks their codes.
 */
class TwoFactorAuthentication
{
    public const RECOVERY_CODE_COUNT = 8;

    public const TRUSTED_DEVICE_DAYS = 30;

    public const TRUSTED_DEVICE_COOKIE = 'trusted_device';

    public function __construct(private Totp $totp) {}

    /**
     * Store a fresh, not yet confirmed secret. Two-factor stays off until confirm() succeeds.
     */
    public function beginSetup(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => $this->totp->generateSecret(),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_step' => null,
        ])->save();
    }

    /**
     * Turn two-factor on when the code matches the pending secret.
     *
     * @return list<string>|null The plain recovery codes to show once, or null when the code is wrong.
     */
    public function confirm(User $user, string $code): ?array
    {
        if ($user->two_factor_secret === null || $user->hasTwoFactorEnabled()) {
            return null;
        }

        $step = $this->totp->verify($user->two_factor_secret, $code);

        if ($step === null || ! $this->claimTimeStep($user, $step, ['two_factor_confirmed_at' => now()], requireUnconfirmed: true)) {
            return null;
        }

        return $this->regenerateRecoveryCodes($user);
    }

    /**
     * Replace the recovery codes (only their hashes are stored) and forget the trusted devices.
     *
     * @return list<string> The plain codes to show once.
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = [];

        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $codes[] = Str::lower(Str::random(5).'-'.Str::random(5));
        }

        $hashes = array_map(fn (string $code): string => Hash::make($code), $codes);

        DB::transaction(function () use ($user, $hashes): void {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $locked->forceFill(['two_factor_recovery_codes' => $hashes])->save();
            $user->setRawAttributes($locked->getAttributes(), true);
        });

        return $codes;
    }

    /**
     * Remember a browser so it can skip the code step after a correct password.
     *
     * @return string The plain token for the cookie; only its hash is stored.
     */
    public function trustDevice(User $user, ?string $ipAddress, ?string $userAgent): string
    {
        $token = Str::random(64);

        $user->trustedDevices()->create([
            'token_hash' => hash('sha256', $token),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent === null ? null : Str::limit($userAgent, 500, ''),
            'expires_at' => now()->addDays(self::TRUSTED_DEVICE_DAYS),
        ]);

        return $token;
    }

    /**
     * Whether the cookie token belongs to a trusted, unexpired device of this user.
     */
    public function isTrustedDevice(User $user, ?string $token): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        return $user->trustedDevices()->valid()->where('token_hash', hash('sha256', $token))->exists();
    }

    /**
     * Turn two-factor off and forget the secret, recovery codes and trusted devices.
     */
    public function disable(User $user): void
    {
        $user->trustedDevices()->delete();

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_step' => null,
        ])->save();
    }

    /**
     * Check an authenticator code or a recovery code. A TOTP time step and a recovery code each work only once.
     */
    public function verify(User $user, string $code): bool
    {
        if (! $user->hasTwoFactorEnabled()) {
            return false;
        }

        $code = Str::lower(trim($code));

        if (preg_match('/^\d[\d ]*$/', $code) === 1) {
            return $this->verifyTotp($user, $code);
        }

        return $this->consumeRecoveryCode($user, $code);
    }

    /**
     * The otpauth:// URI for the user's pending or active secret.
     */
    public function provisioningUri(User $user): string
    {
        return $this->totp->provisioningUri((string) $user->two_factor_secret, $user->email, (string) config('app.name'));
    }

    /**
     * Accept the code only if its time step is newer than the last one used, so a code cannot be replayed.
     */
    private function verifyTotp(User $user, string $code): bool
    {
        $step = $this->totp->verify((string) $user->two_factor_secret, $code);

        return $step !== null && $this->claimTimeStep($user, $step);
    }

    /**
     * Atomically record the time step as used, only if it is newer than the last one (compare-and-set).
     *
     * @param  array<string, mixed>  $extra  More columns to set in the same update.
     */
    private function claimTimeStep(User $user, int $step, array $extra = [], bool $requireUnconfirmed = false): bool
    {
        $updated = User::query()
            ->whereKey($user->getKey())
            ->when($requireUnconfirmed, fn (Builder $query) => $query->whereNull('two_factor_confirmed_at'))
            ->where(function (Builder $query) use ($step): void {
                $query->whereNull('two_factor_last_used_step')->orWhere('two_factor_last_used_step', '<', $step);
            })
            ->update(['two_factor_last_used_step' => $step, ...$extra]);

        if ($updated === 0) {
            return false;
        }

        $columns = ['two_factor_last_used_step' => $step, ...$extra];
        $user->forceFill($columns)->syncOriginalAttributes(array_keys($columns));

        return true;
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        if ($code === '') {
            return false;
        }

        return DB::transaction(function () use ($user, $code): bool {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $hashes = $locked->two_factor_recovery_codes ?? [];

            foreach ($hashes as $index => $hash) {
                if (Hash::check($code, $hash)) {
                    unset($hashes[$index]);
                    $locked->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();
                    $user->setRawAttributes($locked->getAttributes(), true);

                    return true;
                }
            }

            return false;
        });
    }
}

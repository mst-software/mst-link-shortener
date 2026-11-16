<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Turns authenticator-app two-factor authentication on and off for a user and checks their codes.
 */
class TwoFactorAuthentication
{
    public const RECOVERY_CODE_COUNT = 8;

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

        if ($step === null) {
            return null;
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_last_used_step' => $step,
        ])->save();

        return $this->regenerateRecoveryCodes($user);
    }

    /**
     * Replace the recovery codes; only their hashes are stored.
     *
     * @return list<string> The plain codes to show once.
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = [];

        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $codes[] = Str::lower(Str::random(5).'-'.Str::random(5));
        }

        $user->forceFill([
            'two_factor_recovery_codes' => array_map(fn (string $code): string => Hash::make($code), $codes),
        ])->save();

        return $codes;
    }

    /**
     * Turn two-factor off and forget the secret and recovery codes.
     */
    public function disable(User $user): void
    {
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

        if ($step === null) {
            return false;
        }

        $updated = User::query()
            ->whereKey($user->getKey())
            ->where(function ($query) use ($step): void {
                $query->whereNull('two_factor_last_used_step')->orWhere('two_factor_last_used_step', '<', $step);
            })
            ->update(['two_factor_last_used_step' => $step]);

        if ($updated === 0) {
            return false;
        }

        $user->forceFill(['two_factor_last_used_step' => $step])->syncOriginalAttribute('two_factor_last_used_step');

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

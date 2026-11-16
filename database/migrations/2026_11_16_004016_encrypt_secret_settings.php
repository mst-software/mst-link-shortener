<?php

use App\Services\Settings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Secret settings (captcha secret keys) used to be stored in plain text. Encrypt the ones that are not yet.
     */
    public function up(): void
    {
        $this->rewriteSecrets(fn (string $value): ?string => $this->isEncrypted($value) ? null : Crypt::encryptString($value));
    }

    /**
     * Store the secrets in plain text again.
     */
    public function down(): void
    {
        $this->rewriteSecrets(fn (string $value): ?string => $this->isEncrypted($value) ? Crypt::decryptString($value) : null);
    }

    /**
     * @param  callable(string): ?string  $transform  Returns the new value, or null to leave the row alone.
     */
    private function rewriteSecrets(callable $transform): void
    {
        $rows = DB::table('settings')
            ->whereIn('key', Settings::SECRET_KEYS)
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->get(['id', 'value']);

        foreach ($rows as $row) {
            $value = $transform($row->value);

            if ($value !== null) {
                DB::table('settings')->where('id', $row->id)->update(['value' => $value]);
            }
        }

        Cache::forget('settings.all');
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};

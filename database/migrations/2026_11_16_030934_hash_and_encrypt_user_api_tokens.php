<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * API tokens used to be stored in plain text and looked up directly. Store a SHA-256 hash for the
     * lookup and keep the token itself encrypted so members can still see it. Every step checks the
     * current state first, so running it again leaves converted rows alone.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'api_token_hash')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('api_token_hash', 64)->nullable()->unique()->after('api_token');
            });
        }

        if (Schema::hasIndex('users', ['api_token'], 'unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['api_token']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->text('api_token')->change();
        });

        $skipped = 0;

        DB::table('users')->select(['id', 'api_token', 'api_token_hash'])->chunkById(500, function ($users) use (&$skipped): void {
            foreach ($users as $user) {
                $decryptedToken = $this->decryptOrNull($user->api_token);

                if ($decryptedToken === null && $this->looksEncrypted($user->api_token)) {
                    Log::warning('API token migration: the token of user '.$user->id.' is encrypted with another key and was skipped.');
                    $skipped++;

                    continue;
                }

                $plainToken = $decryptedToken ?? $user->api_token;
                $encryptedToken = $plainToken === $user->api_token ? Crypt::encryptString($plainToken) : $user->api_token;
                $hash = hash('sha256', $plainToken);

                if ($encryptedToken !== $user->api_token || $hash !== $user->api_token_hash) {
                    DB::table('users')->where('id', $user->id)->update([
                        'api_token' => $encryptedToken,
                        'api_token_hash' => $hash,
                    ]);
                }
            }
        });

        if ($skipped > 0) {
            Log::warning('API token migration: '.$skipped.' token(s) could not be decrypted and were skipped. Those members can generate a new token on the API tools page.');
        }
    }

    /**
     * Store the tokens in plain text again and drop the hash column. A token that cannot be decrypted
     * is replaced with a new random token, because its ciphertext does not fit the 24 character column.
     */
    public function down(): void
    {
        $replaced = 0;

        DB::table('users')->select(['id', 'api_token'])->chunkById(500, function ($users) use (&$replaced): void {
            foreach ($users as $user) {
                $plainToken = $this->decryptOrNull($user->api_token);

                if ($plainToken === null && $this->looksEncrypted($user->api_token)) {
                    Log::warning('API token rollback: the token of user '.$user->id.' could not be decrypted and was replaced with a new one.');
                    $plainToken = $this->unusedPlainToken();
                    $replaced++;
                }

                if ($plainToken !== null) {
                    DB::table('users')->where('id', $user->id)->update(['api_token' => $plainToken]);
                }
            }
        });

        if ($replaced > 0) {
            Log::warning('API token rollback: '.$replaced.' token(s) could not be decrypted and were replaced.');
        }

        if (Schema::hasColumn('users', 'api_token_hash')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['api_token_hash']);
            });

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('api_token_hash');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('api_token', 24)->change();
        });

        if (! Schema::hasIndex('users', ['api_token'], 'unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('api_token');
            });
        }
    }

    /**
     * Whether the value has the shape of a Laravel encrypted payload (base64 JSON with iv, value and mac).
     */
    private function looksEncrypted(string $value): bool
    {
        $payload = json_decode((string) base64_decode($value, true), true);

        return is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac']);
    }

    /**
     * A random 24 character token that no other row uses.
     */
    private function unusedPlainToken(): string
    {
        do {
            $token = Str::random(24);
        } while (DB::table('users')->where('api_token', $token)->exists());

        return $token;
    }

    /**
     * The decrypted token, or null when the value is a plain token.
     */
    private function decryptOrNull(string $value): ?string
    {
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }
};

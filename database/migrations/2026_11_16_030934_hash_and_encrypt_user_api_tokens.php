<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        DB::table('users')->select(['id', 'api_token', 'api_token_hash'])->chunkById(500, function ($users): void {
            foreach ($users as $user) {
                $plainToken = $this->decryptOrNull($user->api_token) ?? $user->api_token;
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
    }

    /**
     * Store the tokens in plain text again and drop the hash column.
     */
    public function down(): void
    {
        DB::table('users')->select(['id', 'api_token'])->chunkById(500, function ($users): void {
            foreach ($users as $user) {
                $plainToken = $this->decryptOrNull($user->api_token);

                if ($plainToken !== null) {
                    DB::table('users')->where('id', $user->id)->update(['api_token' => $plainToken]);
                }
            }
        });

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

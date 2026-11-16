<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Accounts created before email verification existed are treated as verified,
     * so they are not blocked from withdrawing. Only new signups must verify.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    /**
     * The original verification state of those accounts is not recoverable.
     */
    public function down(): void {}
};

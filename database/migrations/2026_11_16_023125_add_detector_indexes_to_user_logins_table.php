<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_logins', function (Blueprint $table) {
            $table->index(['ua_hash', 'user_id']);
            $table->index(['event', 'ip', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_logins', function (Blueprint $table) {
            $table->dropIndex(['ua_hash', 'user_id']);
            $table->dropIndex(['event', 'ip', 'created_at']);
        });
    }
};

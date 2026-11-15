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
        Schema::table('top_ups', function (Blueprint $table) {
            $table->string('provider_transaction_id')->nullable()->after('reference');

            $table->unique(['provider', 'provider_transaction_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('top_ups', function (Blueprint $table) {
            $table->dropUnique(['provider', 'provider_transaction_id']);
            $table->dropColumn('provider_transaction_id');
        });
    }
};

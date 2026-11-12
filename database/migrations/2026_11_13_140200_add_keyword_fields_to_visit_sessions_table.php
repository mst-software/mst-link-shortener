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
        Schema::table('visit_sessions', function (Blueprint $table) {
            $table->string('mode', 16)->default('wait')->after('level');
            $table->foreignId('campaign_id')->nullable()->after('mode')->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('code_failures')->default(0)->after('campaign_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visit_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
            $table->dropColumn(['mode', 'code_failures']);
        });
    }
};

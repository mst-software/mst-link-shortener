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
        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('review_status', 16)->default('approved')->after('is_active');
            $table->string('review_note')->nullable()->after('review_status');
            $table->unsignedBigInteger('price_micro')->nullable()->after('daily_limit');

            $table->index('review_status');
        });

        Schema::table('views', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('visit_session_id')->constrained()->nullOnDelete();
            $table->unsignedBigInteger('advertiser_cost_micro')->default(0)->after('earn_micro');

            $table->index(['campaign_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('views', function (Blueprint $table) {
            $table->dropIndex(['campaign_id', 'created_at']);
            $table->dropConstrainedForeignId('campaign_id');
            $table->dropColumn('advertiser_cost_micro');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex(['review_status']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['review_status', 'review_note', 'price_micro']);
        });
    }
};

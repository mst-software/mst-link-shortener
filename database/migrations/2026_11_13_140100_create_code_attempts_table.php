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
        Schema::create('code_attempts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('ip', 45);
            $table->char('ua_hash', 64);
            $table->string('fingerprint', 64)->nullable();
            $table->string('referrer_host')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('progressed_at')->nullable();
            $table->json('page_load_ids');
            $table->string('code', 8)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->foreignUlid('visit_session_id')->nullable()->constrained('visit_sessions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['campaign_id', 'code']);
            $table->index(['campaign_id', 'issued_at']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('code_attempts');
    }
};

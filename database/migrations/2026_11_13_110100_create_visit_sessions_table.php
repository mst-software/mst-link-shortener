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
        Schema::create('visit_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('link_id')->constrained()->cascadeOnDelete();
            $table->string('ip', 45);
            $table->char('ua_hash', 64);
            $table->string('fingerprint', 64)->nullable();
            $table->char('country', 2)->default('XX');
            $table->unsignedTinyInteger('level');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->timestamp('step_issued_at')->nullable();
            $table->char('token_hash', 64)->nullable();
            $table->string('status', 16)->default('in_progress');
            $table->timestamp('expires_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
            $table->index(['link_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visit_sessions');
    }
};

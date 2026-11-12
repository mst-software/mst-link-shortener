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
        Schema::create('views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('visit_session_id')->unique()->constrained('visit_sessions')->cascadeOnDelete();
            $table->string('ip', 45);
            $table->char('country', 2)->default('XX');
            $table->unsignedTinyInteger('level');
            $table->string('status', 16)->default('pending');
            $table->string('invalid_reason', 32)->nullable();
            $table->unsignedBigInteger('earn_micro')->default(0);
            $table->timestamp('matures_at')->nullable();
            $table->timestamp('aggregated_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index(['link_id', 'created_at']);
            $table->index(['status', 'matures_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('views');
    }
};

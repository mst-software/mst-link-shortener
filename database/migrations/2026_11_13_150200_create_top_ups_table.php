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
        Schema::create('top_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 16);
            $table->string('reference');
            $table->string('status', 16)->default('pending');
            $table->unsignedBigInteger('amount_micro');
            $table->unsignedBigInteger('original_amount');
            $table->string('original_currency', 3);
            $table->unsignedInteger('exchange_rate')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'reference']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('top_ups');
    }
};

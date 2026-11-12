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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 3);
            $table->string('action', 40);
            $table->unsignedBigInteger('amount_micro');
            $table->unsignedBigInteger('balance_before_micro');
            $table->unsignedBigInteger('balance_after_micro');
            $table->string('reference_id')->nullable();
            $table->string('description')->default('');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('reference_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

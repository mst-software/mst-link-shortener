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
        Schema::create('fraud_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('link_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 64);
            $table->json('details')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('resolved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fraud_flags');
    }
};

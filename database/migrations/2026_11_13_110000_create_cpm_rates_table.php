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
        Schema::create('cpm_rates', function (Blueprint $table) {
            $table->id();
            $table->char('country', 2);
            $table->unsignedTinyInteger('level');
            $table->unsignedBigInteger('rate_per_1000_micro');
            $table->timestamps();

            $table->unique(['country', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cpm_rates');
    }
};

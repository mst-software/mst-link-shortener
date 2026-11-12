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
        Schema::create('links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $alias = $table->string('alias', 32);

            // Aliases are case-sensitive; MySQL needs a binary collation, SQLite is already case-sensitive.
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $alias->collation('utf8mb4_bin');
            }

            $alias->unique();

            $table->text('url');
            $table->unsignedTinyInteger('level')->default(1);
            $table->string('status', 16)->default('active');
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('links');
    }
};

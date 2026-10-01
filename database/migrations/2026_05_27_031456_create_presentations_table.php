<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->enum('status', ['draft', 'published', 'sold_out', 'cancelled'])->default('published');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedInteger('capacity');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['season_id', 'starts_at']);

            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('show_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('people')->restrictOnDelete();
            $table->enum('section', ['cast', 'technical']);
            $table->string('character_name')->nullable();
            $table->string('display_name_override')->nullable();
            $table->string('role_label');
            $table->string('photo_path_override')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['show_id', 'section', 'sort_order']);
            $table->index(['person_id', 'show_id']);
            $table->index(['role_label', 'section']);

            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('show_credits');
    }
};

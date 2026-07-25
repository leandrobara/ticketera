<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('show_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->cascadeOnDelete();
            $table->string('path');
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->enum('type', ['gallery', 'grid'])->default('gallery');
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_main')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['show_id', 'type', 'sort_order']);
            $table->index(['show_id', 'is_main']);

            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('show_images');
    }
};

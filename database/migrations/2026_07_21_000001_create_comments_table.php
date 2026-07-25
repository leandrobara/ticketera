<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('buyers')->cascadeOnDelete();
            $table->foreignId('show_id')->constrained('shows')->cascadeOnDelete();
            $table->string('name', 160);
            $table->unsignedTinyInteger('rating');
            $table->text('comment');
            $table->enum('status', ['visible', 'hidden'])->default('visible')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['buyer_id', 'show_id']);
            $table->index(['show_id', 'status', 'created_at']);

            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('provider')->default('MERCADO_PAGO');
            $table->string('provider_payment_id')->nullable()->index();
            $table->string('provider_preference_id')->nullable()->index();
            $table->string('provider_status')->nullable()->index();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('ARS');
            $table->json('raw_response')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['show_id', 'order_id', 'provider']);

            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->cascadeOnDelete();
            $table->foreignId('presentation_id')->constrained('presentations')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('buyers')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code')->unique();
            $table->enum('source', ['CHECKOUT', 'ADMIN'])->default('CHECKOUT');
            $table->enum('status', [
                'PENDING',
                'APPROVED',
                'REJECTED',
                'IN_PROCESS',
                'WAIVED',
                'CANCELED',
                'EXPIRED',
                'REFUNDED',
            ])->default('PENDING')->index();
            $table->enum('payment_method', [
                'MERCADO_PAGO',
                'CASH',
                'BANK_TRANSFER',
                'COMPLIMENTARY',
                'OTHER',
            ])->default('MERCADO_PAGO');
            $table->unsignedInteger('total_quantity');
            $table->unsignedInteger('total_amount');
            $table->string('currency', 3)->default('ARS');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['presentation_id', 'status']);
            $table->index(['show_id', 'buyer_id', 'created_at']);

            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

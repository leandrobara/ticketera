<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('presentation_ticket_type_id')->nullable()->constrained('presentation_ticket_types')->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('paid_quantity');
            $table->decimal('unit_price', 18, 6);
            $table->decimal('unit_service_fee', 18, 6)->default(0);
            $table->string('service_fee_type')->nullable();
            $table->decimal('service_fee_fixed_amount', 18, 6)->nullable();
            $table->decimal('service_fee_percentage', 18, 6)->nullable();
            $table->decimal('service_fee_base_amount', 18, 6)->default(0);
            $table->boolean('service_fee_minimum_applied')->default(false);
            $table->decimal('service_fee_minimum_unit_amount', 18, 6)->nullable();
            $table->decimal('subtotal_amount', 18, 6);
            $table->decimal('discount_amount', 18, 6)->default(0);
            $table->decimal('service_fee_total_amount', 18, 6)->default(0);
            $table->decimal('total_amount', 18, 6);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['show_id', 'order_id', 'presentation_ticket_type_id']);

            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};

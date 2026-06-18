<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')
                ->unique()
                ->constrained('order_items')
                ->cascadeOnDelete();
            $table->foreignId('promotion_id')
                ->nullable()
                ->constrained('promotions')
                ->nullOnDelete();
            $table->string('promotion_name');
            $table->enum('promotion_type', [
                'percent_discount',
                'fixed_discount',
                'buy_x_get_y',
            ]);
            $table->decimal('promotion_value', 18, 6)->nullable();
            $table->string('promotion_access_code', 80)->nullable();
            $table->unsignedInteger('bundle_quantity')->nullable();
            $table->unsignedInteger('pay_quantity')->nullable();
            $table->decimal('discount_amount', 18, 6);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['promotion_id', 'promotion_type']);

            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_promotions');
    }
};

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentation_ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_id')->constrained('presentations')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 18, 6);
            $table->unsignedInteger('stock')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->nullable(false)->default(1);
            $table->string('promotion_name')->nullable();
            $table->enum('promotion_type', [
                'percent_discount',
                'fixed_discount',
                'buy_x_get_y',
            ])->nullable();
            $table->decimal('promotion_value', 18, 6)->nullable();
            $table->unsignedInteger('promotion_bundle_quantity')->nullable();
            $table->unsignedInteger('promotion_pay_quantity')->nullable();
            $table->string('promotion_access_code', 80)->nullable();
            $table->boolean('promotion_is_active')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index('promotion_access_code');

            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_ticket_types');
    }
};

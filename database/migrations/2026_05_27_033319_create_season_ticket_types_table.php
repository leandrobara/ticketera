<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('seasons')->cascadeOnDelete();
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

            $table->string('active_promotion_code', 80)->nullable()->storedAs('CASE WHEN deleted_at IS NULL THEN promotion_access_code ELSE NULL END');
            $table->unique(['season_id', 'active_promotion_code'], 'season_promotion_code_unique');

            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_ticket_types');
    }
};

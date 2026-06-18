<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_ticket_type_id')->constrained('presentation_ticket_types')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', [
                'percent_discount',
                'fixed_discount',
                'buy_x_get_y',
            ]);
            $table->decimal('value', 18, 6)->nullable();
            $table->unsignedInteger('bundle_quantity')->nullable();
            $table->unsignedInteger('pay_quantity')->nullable();
            $table->string('access_code', 80)->nullable()->unique();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('presentation_ticket_type_id');
            $table->index(['is_active', 'starts_at', 'ends_at']);

            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};

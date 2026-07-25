<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shows', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('synopsis')->nullable();
            $table->text('additional_information')->nullable();
            $table->text('production_note')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('x_url')->nullable();
            $table->string('tiktok_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('pinterest_url')->nullable();
            $table->string('website_url')->nullable();
            $table->json('faqs')->nullable();
            $table->string('slug')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('genre')->nullable();
            $table->string('format')->nullable();
            $table->enum('age_rating', ['ATP', '+13', '+16', '+18'])->default('ATP');
            $table->enum('service_fee_type', ['fixed_amount', 'percentage'])->default('fixed_amount');
            $table->decimal('service_fee_fixed_amount', 18, 6)->nullable();
            $table->decimal('service_fee_percentage', 18, 6)->nullable();
            $table->decimal('service_fee_minimum_unit_amount', 18, 6)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shows');
    }
};

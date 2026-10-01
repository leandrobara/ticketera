<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('configuration');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_schedules');
    }
};

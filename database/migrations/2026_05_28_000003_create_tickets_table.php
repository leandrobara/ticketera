<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('presentation_id')->constrained('presentations')->cascadeOnDelete();
            $table->foreignId('presentation_ticket_type_id')->nullable()->constrained('presentation_ticket_types')->nullOnDelete();
            $table->string('code')->unique();
            $table->enum('status', ['VALID', 'USED', 'CANCELED'])->default('VALID')->index();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('canceled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['show_id', 'presentation_id', 'status']);
            $table->index(['presentation_ticket_type_id', 'status']);

            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};

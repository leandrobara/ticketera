<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_user_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 160);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('organization_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->text('encrypted_token');
            $table->json('roles');
            $table->json('show_ids')->nullable();
            $table->unsignedInteger('max_uses')->default(1);
            $table->unsignedInteger('uses')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('organization_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('invitation_id')->nullable()->constrained('organization_invitations')->nullOnDelete();
            $table->json('roles');
            $table->json('show_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('joined_at');
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_memberships');
        Schema::dropIfExists('organization_invitations');
        Schema::dropIfExists('organizations');
    }
};

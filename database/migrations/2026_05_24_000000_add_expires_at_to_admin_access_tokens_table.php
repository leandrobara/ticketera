<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_access_tokens', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('last_used_at')->index();
        });

        DB::table('admin_access_tokens')
            ->whereNull('expires_at')
            ->update([
                'expires_at' => now()->addMinutes(config('auth.admin_access_tokens.ttl_minutes', 480)),
            ]);
    }

    public function down(): void
    {
        Schema::table('admin_access_tokens', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};

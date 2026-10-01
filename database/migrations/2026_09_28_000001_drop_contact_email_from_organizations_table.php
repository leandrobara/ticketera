<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('organizations', 'contact_email')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('contact_email');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('organizations', 'contact_email')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->string('contact_email')->nullable();
            });
        }
    }
};

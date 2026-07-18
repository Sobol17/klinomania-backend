<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_profiles', function (Blueprint $table): void {
            $table->string('fcm_token', 4096)->nullable()->unique()->after('push_notifications_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('client_profiles', function (Blueprint $table): void {
            $table->dropUnique(['fcm_token']);
            $table->dropColumn('fcm_token');
        });
    }
};

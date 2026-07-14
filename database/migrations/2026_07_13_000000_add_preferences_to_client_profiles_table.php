<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_profiles', function (Blueprint $table): void {
            $table->string('address')->nullable()->after('name');
            $table->boolean('push_notifications_enabled')->default(false)->after('address');
            $table->boolean('email_marketing_enabled')->default(false)->after('push_notifications_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('client_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'address',
                'push_notifications_enabled',
                'email_marketing_enabled',
            ]);
        });
    }
};

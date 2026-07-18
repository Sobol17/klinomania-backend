<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cleaning_services', function (Blueprint $table): void {
            $table->unsignedInteger('cleaner_base_earnings')->default(0)->after('base_price');
        });

        Schema::table('service_options', function (Blueprint $table): void {
            $table->unsignedTinyInteger('cleaner_revenue_percent')->default(0)->after('price_modifier');
        });

        Schema::table('order_line_items', function (Blueprint $table): void {
            $table->unsignedInteger('cleaner_earnings')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('order_line_items', function (Blueprint $table): void {
            $table->dropColumn('cleaner_earnings');
        });

        Schema::table('service_options', function (Blueprint $table): void {
            $table->dropColumn('cleaner_revenue_percent');
        });

        Schema::table('cleaning_services', function (Blueprint $table): void {
            $table->dropColumn('cleaner_base_earnings');
        });
    }
};

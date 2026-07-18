<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_checklist_items', function (Blueprint $table): void {
            $table->string('zone', 20)->default('all')->after('cleaning_service_id');
            $table->index(['cleaning_service_id', 'zone', 'sort_order'], 'service_checklist_zone_sort_index');
        });
    }

    public function down(): void
    {
        Schema::table('service_checklist_items', function (Blueprint $table): void {
            $table->dropIndex('service_checklist_zone_sort_index');
            $table->dropColumn('zone');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_options', function (Blueprint $table): void {
            $table->string('checklist_zone', 20)->default('all')->after('group');
        });

        Schema::create('order_extra_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cleaning_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_line_item_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cleaning_order_id', 'order_line_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_extra_checklist_items');

        Schema::table('service_options', function (Blueprint $table): void {
            $table->dropColumn('checklist_zone');
        });
    }
};

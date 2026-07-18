<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cleaning_service_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['cleaning_service_id', 'sort_order']);
        });

        Schema::create('order_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cleaning_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_checklist_item_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cleaning_order_id', 'service_checklist_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_checklist_items');
        Schema::dropIfExists('service_checklist_items');
    }
};

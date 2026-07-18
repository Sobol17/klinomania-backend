<?php

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cleaning_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cleaner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cleaning_service_id')->constrained()->restrictOnDelete();
            $table->string('status')->default(OrderStatus::Processing->value)->index();
            $table->string('address');
            $table->timestamp('scheduled_at')->nullable();
            $table->text('comment')->nullable();
            $table->unsignedInteger('total_price');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cleaning_orders');
    }
};

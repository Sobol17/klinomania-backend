<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cleaning_order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32)->index();
            $table->string('external_order_id', 50)->unique();
            $table->string('provider_payment_id')->nullable()->unique();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('RUB');
            $table->text('payment_url')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('status', 32)->index();
            $table->string('provider_status', 32)->nullable();
            $table->string('error_code', 32)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cleaning_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_quote_id');
            $table->string('request_hash', 64)->nullable()->after('idempotency_key');
        });

        Schema::dropIfExists('service_quotes');
    }

    public function down(): void
    {
        Schema::create('service_quotes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cleaning_service_id')->constrained()->restrictOnDelete();
            $table->json('configuration');
            $table->json('line_items');
            $table->json('service_snapshot');
            $table->unsignedInteger('total_price');
            $table->string('currency', 3);
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('cleaning_orders', function (Blueprint $table): void {
            $table->dropColumn('request_hash');
            $table->foreignUlid('service_quote_id')->nullable()->constrained('service_quotes')->restrictOnDelete();
        });
    }
};

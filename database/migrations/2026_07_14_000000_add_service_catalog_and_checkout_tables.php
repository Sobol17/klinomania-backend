<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cleaning_services', function (Blueprint $table): void {
            $table->string('slug')->nullable()->unique()->after('id');
            $table->string('subtitle')->nullable()->after('name');
            $table->text('short_description')->nullable()->after('description');
            $table->text('long_description')->nullable()->after('short_description');
            $table->string('cleaners_label')->nullable();
            $table->string('duration_label')->nullable();
            $table->string('image_url')->nullable();
            $table->json('gallery')->nullable();
            $table->unsignedInteger('price_per_sqm')->default(0);
            $table->unsignedInteger('min_area')->default(1);
            $table->unsignedInteger('max_area')->default(1000);
            $table->unsignedInteger('area_step')->default(1);
            $table->unsignedInteger('min_price')->default(0);
            $table->string('currency', 3)->default('RUB');
            $table->unsignedInteger('sort_order')->default(0)->index();
        });

        Schema::create('service_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cleaning_service_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('group');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->boolean('is_addon')->default(false);
            $table->boolean('is_default')->default(false);
            $table->integer('price_modifier')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['cleaning_service_id', 'code']);
        });

        Schema::create('service_option_dependencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_option_id')->constrained('service_options')->cascadeOnDelete();
            $table->foreignId('allowed_with_option_id')->constrained('service_options')->cascadeOnDelete();
            $table->unique(['service_option_id', 'allowed_with_option_id'], 'service_option_dependency_unique');
        });

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
            $table->ulid('public_id')->nullable()->unique()->after('id');
            $table->foreignUlid('service_quote_id')->nullable()->constrained('service_quotes')->restrictOnDelete();
            $table->string('idempotency_key', 64)->nullable();
            $table->string('currency', 3)->default('RUB');
            $table->json('service_snapshot')->nullable();
            $table->unique(['client_id', 'idempotency_key']);
        });

        Schema::create('order_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cleaning_order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('full_address');
            $table->string('fias_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('entrance')->nullable();
            $table->string('floor')->nullable();
            $table->string('apartment')->nullable();
            $table->string('intercom')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('order_line_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cleaning_order_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('source_option_id')->nullable();
            $table->string('title');
            $table->integer('amount');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_line_items');
        Schema::dropIfExists('order_addresses');
        Schema::table('cleaning_orders', function (Blueprint $table): void {
            $table->dropUnique(['client_id', 'idempotency_key']);
            $table->dropConstrainedForeignId('service_quote_id');
            $table->dropColumn(['public_id', 'idempotency_key', 'currency', 'service_snapshot']);
        });
        Schema::dropIfExists('service_quotes');
        Schema::dropIfExists('service_option_dependencies');
        Schema::dropIfExists('service_options');
        Schema::table('cleaning_services', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'subtitle', 'short_description', 'long_description', 'cleaners_label', 'duration_label', 'image_url', 'gallery', 'price_per_sqm', 'min_area', 'max_area', 'area_step', 'min_price', 'currency', 'sort_order']);
        });
    }
};

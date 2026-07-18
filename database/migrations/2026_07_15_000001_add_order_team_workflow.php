<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cleaning_services', function (Blueprint $table): void {
            $table->unsignedTinyInteger('required_cleaners')->default(1)->after('cleaners_label');
        });

        DB::table('cleaning_services')->where('slug', 'premium')->update(['required_cleaners' => 2]);
        DB::table('cleaning_services')->where('slug', 'cottage')->update(['required_cleaners' => 3]);
        DB::table('cleaning_orders')->whereIn('status', ['pending', 'awaiting_cleaner'])->update(['status' => 'processing']);
        DB::table('cleaning_orders')->where('status', 'accepted')->update(['status' => 'team_formed']);

        Schema::create('cleaning_order_cleaners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cleaning_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cleaner_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['cleaning_order_id', 'cleaner_id']);
        });

        DB::table('cleaning_orders')->whereNotNull('cleaner_id')->orderBy('id')->each(function (object $order): void {
            DB::table('cleaning_order_cleaners')->insert([
                'cleaning_order_id' => $order->id,
                'cleaner_id' => $order->cleaner_id,
                'accepted_at' => $order->created_at,
                'started_at' => $order->status === 'in_progress' || $order->status === 'completed' ? $order->updated_at : null,
                'completed_at' => $order->status === 'completed' ? $order->updated_at : null,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ]);
        });

        Schema::table('cleaning_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cleaner_id');
        });
    }

    public function down(): void
    {
        Schema::table('cleaning_orders', function (Blueprint $table): void {
            $table->foreignId('cleaner_id')->nullable()->constrained('users')->nullOnDelete();
        });

        DB::table('cleaning_order_cleaners')->orderBy('id')->each(function (object $member): void {
            DB::table('cleaning_orders')->where('id', $member->cleaning_order_id)->whereNull('cleaner_id')->update(['cleaner_id' => $member->cleaner_id]);
        });

        Schema::dropIfExists('cleaning_order_cleaners');
        DB::table('cleaning_orders')->where('status', 'processing')->update(['status' => 'pending']);
        DB::table('cleaning_orders')->where('status', 'team_formed')->update(['status' => 'accepted']);

        Schema::table('cleaning_services', function (Blueprint $table): void {
            $table->dropColumn('required_cleaners');
        });
    }
};

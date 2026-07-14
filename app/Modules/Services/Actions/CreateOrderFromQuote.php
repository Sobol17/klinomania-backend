<?php

namespace App\Modules\Services\Actions;

use App\Enums\OrderStatus;
use App\Models\CleaningOrder;
use App\Models\ServiceQuote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateOrderFromQuote
{
    /** @param array<string, mixed> $input */
    public function execute(User $user, string $idempotencyKey, array $input): CleaningOrder
    {
        return DB::transaction(function () use ($user, $idempotencyKey, $input): CleaningOrder {
            $existing = CleaningOrder::query()->where('client_id', $user->id)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->service_quote_id === $input['quote_id']) {
                    return $existing;
                }
                abort(response()->json(['message' => 'Idempotency key is already used with a different request.', 'code' => 'idempotency_key_conflict'], 409));
            }
            $quote = ServiceQuote::query()->whereKey($input['quote_id'])->where('user_id', $user->id)->lockForUpdate()->first();
            if ($quote === null) {
                abort(response()->json(['message' => 'Quote not found.', 'code' => 'quote_not_found'], 404));
            }
            if ($quote->used_at !== null) {
                abort(response()->json(['message' => 'Quote already used.', 'code' => 'quote_already_used'], 409));
            }
            if ($quote->expires_at->isPast()) {
                abort(response()->json(['message' => 'Quote expired.', 'code' => 'quote_expired'], 410));
            }

            $order = CleaningOrder::create([
                'public_id' => (string) Str::ulid(), 'client_id' => $user->id, 'cleaning_service_id' => $quote->cleaning_service_id,
                'service_quote_id' => $quote->id, 'idempotency_key' => $idempotencyKey, 'status' => OrderStatus::AwaitingCleaner,
                'address' => $input['address']['full_address'], 'scheduled_at' => $input['scheduled_at'], 'comment' => $input['address']['comment'] ?? null,
                'total_price' => $quote->total_price, 'currency' => $quote->currency, 'service_snapshot' => $quote->service_snapshot,
            ]);
            $order->addressSnapshot()->create($input['address']);
            $order->lineItems()->createMany(array_map(fn (array $item) => ['kind' => $item['kind'], 'source_option_id' => $item['option_id'] ?? null, 'title' => $item['title'], 'amount' => $item['amount']], $quote->line_items));
            $quote->forceFill(['used_at' => now()])->save();

            return $order;
        });
    }
}

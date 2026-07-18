<?php

namespace App\Modules\Orders\Actions;

use App\Enums\OrderStatus;
use App\Models\CleaningOrder;
use App\Models\CleaningService;
use App\Models\ServiceOption;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateOrder
{
    /** @param array<string, mixed> $input */
    public function execute(User $user, string $idempotencyKey, string $requestHash, array $input): CleaningOrder
    {
        return DB::transaction(function () use ($user, $idempotencyKey, $requestHash, $input): CleaningOrder {
            $existing = CleaningOrder::query()
                ->where('client_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if (hash_equals((string) $existing->request_hash, $requestHash)) {
                    return $existing;
                }

                abort(response()->json([
                    'message' => 'Idempotency key is already used with a different request.',
                    'code' => 'idempotency_key_conflict',
                ], 409));
            }

            $service = CleaningService::query()
                ->where('slug', $input['service_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if ($service === null) {
                abort(response()->json(['message' => 'Service not found.', 'code' => 'service_not_found'], 404));
            }

            $selectedCodes = array_values(array_filter([
                $input['room_option_id'] ?? null,
                $input['cleaning_option_id'] ?? null,
                ...$input['extra_option_ids'],
            ]));

            $options = ServiceOption::query()
                ->where('cleaning_service_id', $service->id)
                ->where('is_active', true)
                ->whereIn('code', $selectedCodes)
                ->get()
                ->keyBy('code');

            if ($options->count() !== count($selectedCodes)) {
                throw ValidationException::withMessages(['extra_option_ids' => ['One or more options are unavailable.']]);
            }

            $roomCode = $input['room_option_id'] ?? null;
            $cleaningCode = $input['cleaning_option_id'] ?? null;
            $room = $roomCode === null ? null : $options[$roomCode];
            $cleaning = $cleaningCode === null ? null : $options[$cleaningCode];
            $extras = collect($input['extra_option_ids'])->map(fn (string $code) => $options[$code]);

            if (($room !== null && ($room->group !== 'room' || $room->is_addon))
                || ($cleaning !== null && ($cleaning->group !== 'cleaning' || $cleaning->is_addon))
                || $extras->contains(fn (ServiceOption $option) => $option->group !== 'extra' || ! $option->is_addon)) {
                throw ValidationException::withMessages(['extra_option_ids' => ['The selected configuration is invalid.']]);
            }

            $selectedOptionIds = $options->pluck('id');
            foreach ($options as $option) {
                $allowedWith = $option->allowedWith()->pluck('service_options.id');
                if ($allowedWith->isNotEmpty() && $allowedWith->intersect($selectedOptionIds)->isEmpty()) {
                    throw ValidationException::withMessages(['extra_option_ids' => ["Option {$option->code} is incompatible with the selected configuration."]]);
                }
            }

            $lineItems = [[
                'kind' => 'base',
                'title' => $service->name,
                'amount' => max($service->base_price, $service->min_price),
                'cleaner_earnings' => $service->cleaner_base_earnings,
            ]];
            foreach ([[$room, 'room_option'], [$cleaning, 'cleaning_option']] as [$option, $kind]) {
                if ($option !== null) {
                    $lineItems[] = ['kind' => $kind, 'option_id' => $option->code, 'title' => $option->title, 'amount' => $option->price_modifier, 'cleaner_earnings' => 0];
                }
            }
            foreach ($extras as $extra) {
                $lineItems[] = [
                    'kind' => 'extra_option',
                    'option_id' => $extra->code,
                    'title' => $extra->title,
                    'amount' => $extra->price_modifier,
                    'cleaner_earnings' => (int) round($extra->price_modifier * $extra->cleaner_revenue_percent / 100, 0, PHP_ROUND_HALF_UP),
                ];
            }

            $order = CleaningOrder::create([
                'public_id' => (string) Str::ulid(),
                'client_id' => $user->id,
                'cleaning_service_id' => $service->id,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'status' => OrderStatus::Processing,
                'address' => $input['address']['full_address'],
                'scheduled_at' => $input['scheduled_at'],
                'comment' => $input['address']['comment'] ?? null,
                'total_price' => array_sum(array_column($lineItems, 'amount')),
                'currency' => 'RUB',
                'service_snapshot' => ['id' => $service->slug, 'title' => $service->name],
            ]);
            $order->addressSnapshot()->create($input['address']);
            $order->lineItems()->createMany(array_map(
                fn (array $item) => ['kind' => $item['kind'], 'source_option_id' => $item['option_id'] ?? null, 'title' => $item['title'], 'amount' => $item['amount'], 'cleaner_earnings' => $item['cleaner_earnings']],
                $lineItems,
            ));

            return $order;
        });
    }
}

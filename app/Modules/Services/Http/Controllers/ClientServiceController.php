<?php

namespace App\Modules\Services\Http\Controllers;

use App\Enums\ChecklistZone;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CleaningOrder;
use App\Models\CleaningService;
use App\Models\User;
use App\Modules\Orders\Actions\CreateOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ClientServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureClient($request);
        $services = CleaningService::query()->where('is_active', true)->whereNotNull('slug')->orderBy('sort_order')->get();

        return response()->json(['data' => $services->map(fn (CleaningService $service) => $this->serviceCard($service))->values()]);
    }

    public function show(Request $request, string $serviceId): JsonResponse
    {
        $this->ensureClient($request);
        $service = CleaningService::query()->where('slug', $serviceId)->where('is_active', true)->first();
        if ($service === null) {
            return response()->json(['message' => 'Service not found.', 'code' => 'service_not_found'], 404);
        }
        $options = $service->options()->where('is_active', true)->orderBy('sort_order')->get();
        $checklist = $service->checklistItems()->get()->map(fn ($item) => $this->checklistItem($item))->values();
        $data = $this->serviceCard($service) + [
            'description' => $service->long_description ?? $service->description,
            'pricing' => ['base_price' => $service->base_price, 'price_per_sqm' => $service->price_per_sqm, 'min_area' => $service->min_area, 'max_area' => $service->max_area, 'area_step' => $service->area_step, 'min_price' => $service->min_price, 'currency' => $service->currency],
            'checklist' => $checklist,
            'checklist_sections' => $this->checklistSections($checklist),
            'room_options' => $options->where('group', 'room')->map(fn ($option) => $this->option($option))->values(),
            'cleaning_options' => $options->where('group', 'cleaning')->map(fn ($option) => $this->option($option))->values(),
            'extra_options' => $options->where('group', 'extra')->map(fn ($option) => $this->option($option))->values(),
        ];

        return response()->json(['data' => $data]);
    }

    public function createOrder(Request $request, CreateOrder $action): JsonResponse
    {
        $user = $this->ensureClient($request);
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $key)) {
            return response()->json(['message' => 'The Idempotency-Key header must be a UUID v4.', 'code' => 'validation_error', 'errors' => ['Idempotency-Key' => ['A UUID v4 is required.']]], 422);
        }
        try {
            $input = $this->validatedOrderInput($request);
            $selectedCodes = array_filter([$input['room_option_id'] ?? null, $input['cleaning_option_id'] ?? null, ...$input['extra_option_ids']]);
            if (count($selectedCodes) !== count(array_unique($selectedCodes))) {
                throw ValidationException::withMessages(['extra_option_ids' => ['Each option may only be selected once.']]);
            }

            $order = $action->execute($user, $key, $this->requestHash($input), $input);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'code' => 'validation_error',
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json(['data' => $this->orderResponse($order)], $order->wasRecentlyCreated ? 201 : 200);
    }

    public function homeSummary(Request $request): JsonResponse
    {
        $user = $this->ensureClient($request);
        $orders = CleaningOrder::query()->where('client_id', $user->id)->whereIn('status', [OrderStatus::Processing, OrderStatus::Confirmed, OrderStatus::TeamFormed, OrderStatus::InProgress, OrderStatus::AwaitingPayment])->orderBy('scheduled_at')->get();
        $order = $orders->first();
        $labels = [
            OrderStatus::Processing->value => 'В обработке',
            OrderStatus::Confirmed->value => 'Подтверждена',
            OrderStatus::TeamFormed->value => 'Команда сформирована',
            OrderStatus::InProgress->value => 'В работе',
            OrderStatus::AwaitingPayment->value => 'Ожидает оплаты',
        ];

        return response()->json(['data' => ['active_orders_count' => $orders->count(), 'active_order_status' => $order?->status?->value, 'active_order_status_label' => $order === null ? null : $labels[$order->status->value]]]);
    }

    private function ensureClient(Request $request): User
    {
        if ($request->user()->role !== UserRole::Client) {
            abort(response()->json(['message' => 'Forbidden.', 'code' => 'forbidden'], 403));
        }

        return $request->user();
    }

    private function serviceCard(CleaningService $service): array
    {
        return ['id' => $service->slug, 'slug' => $service->slug, 'title' => $service->name, 'subtitle' => $service->subtitle, 'short_description' => $service->short_description, 'cleaners_label' => $service->cleaners_label, 'duration_label' => $service->duration_label, 'price_from' => max($service->base_price, $service->min_price), 'image_url' => $service->image_url, 'gallery' => $service->gallery ?? [], 'created_at' => $service->created_at, 'updated_at' => $service->updated_at];
    }

    private function option($option): array
    {
        return ['id' => $option->code, 'title' => $option->title, 'subtitle' => $option->subtitle, 'is_addon' => $option->is_addon, 'default' => $option->is_default, 'price_modifier' => $option->price_modifier, 'sort_order' => $option->sort_order];
    }

    private function checklistItem($item): array
    {
        return ['id' => $item->id, 'zone' => $item->zone->value, 'title' => $item->title, 'sort_order' => $item->sort_order];
    }

    /** @param Collection<int, array<string, mixed>> $checklist */
    private function checklistSections(Collection $checklist): array
    {
        return array_map(fn (ChecklistZone $zone): array => [
            'id' => $zone->value,
            'title' => $zone->label(),
            'items' => $checklist->where('zone', $zone->value)->values(),
        ], ChecklistZone::cases());
    }

    /** @return array<string, mixed> */
    private function validatedOrderInput(Request $request): array
    {
        $payload = $request->all();
        if (array_key_exists('comment', $payload) && is_array($payload['address'] ?? null) && ! array_key_exists('comment', $payload['address'])) {
            $payload['address']['comment'] = $payload['comment'];
            unset($payload['comment']);
        }
        foreach (['room_option_id', 'cleaning_option_id', 'payment_method'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $this->nullableTrimmedString($payload[$field]);
            }
        }
        foreach (['entrance', 'floor', 'apartment', 'intercom', 'comment'] as $field) {
            if (array_key_exists($field, $payload['address'] ?? [])) {
                $payload['address'][$field] = $this->nullableTrimmedString($payload['address'][$field]);
            }
        }
        if (array_key_exists('full_address', $payload['address'] ?? [])) {
            $payload['address']['full_address'] = is_string($payload['address']['full_address']) ? trim($payload['address']['full_address']) : $payload['address']['full_address'];
        }
        if (is_string($payload['scheduled_at'] ?? null)
            && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?$/', $payload['scheduled_at'])) {
            $payload['scheduled_at'] .= 'Z';
        }
        $request->replace($payload);

        return $request->validate([
            'service_id' => ['required', 'string', 'max:100'],
            'room_option_id' => ['nullable', 'string', 'max:100'],
            'cleaning_option_id' => ['nullable', 'string', 'max:100'],
            'extra_option_ids' => ['present', 'array'],
            'extra_option_ids.*' => ['string', 'max:100', 'distinct:strict'],
            'scheduled_at' => ['required', 'string', 'regex:/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(?:\\.\\d+)?(?:Z|[+-]\\d{2}:\\d{2})$/', 'date', 'after_or_equal:now', 'before_or_equal:'.now()->addYear()->toDateTimeString()],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'array'],
            'address.full_address' => ['required', 'string', 'min:3', 'max:255'],
            'address.entrance' => ['nullable', 'string', 'max:50'],
            'address.floor' => ['nullable', 'string', 'max:50'],
            'address.apartment' => ['nullable', 'string', 'max:50'],
            'address.intercom' => ['nullable', 'string', 'max:50'],
            'address.comment' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function nullableTrimmedString(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $input */
    private function requestHash(array $input): string
    {
        $canonicalize = function (mixed $value) use (&$canonicalize): mixed {
            if (! is_array($value)) {
                return $value;
            }
            if (array_is_list($value)) {
                return array_map($canonicalize, $value);
            }
            ksort($value);

            return array_map($canonicalize, $value);
        };

        return hash('sha256', json_encode($canonicalize($input), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function orderResponse(CleaningOrder $order): array
    {
        return ['id' => $order->public_id, 'status' => $order->status->value, 'status_label' => 'В обработке', 'scheduled_at' => $order->scheduled_at, 'total_price' => $order->total_price, 'currency' => $order->currency, 'service' => $order->service_snapshot, 'created_at' => $order->created_at];
    }
}

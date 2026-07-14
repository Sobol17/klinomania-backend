<?php

namespace App\Modules\Services\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CleaningOrder;
use App\Models\CleaningService;
use App\Models\ServiceQuote;
use App\Models\User;
use App\Modules\Services\Actions\CalculateServiceQuote;
use App\Modules\Services\Actions\CreateOrderFromQuote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $data = $this->serviceCard($service) + [
            'description' => $service->long_description ?? $service->description,
            'pricing' => ['base_price' => $service->base_price, 'price_per_sqm' => $service->price_per_sqm, 'min_area' => $service->min_area, 'max_area' => $service->max_area, 'area_step' => $service->area_step, 'min_price' => $service->min_price, 'currency' => $service->currency],
            'room_options' => $options->where('group', 'room')->map(fn ($option) => $this->option($option))->values(),
            'cleaning_options' => $options->where('group', 'cleaning')->map(fn ($option) => $this->option($option))->values(),
            'extra_options' => $options->where('group', 'extra')->map(fn ($option) => $this->option($option))->values(),
        ];

        return response()->json(['data' => $data]);
    }

    public function quote(Request $request, CalculateServiceQuote $action): JsonResponse
    {
        $user = $this->ensureClient($request);
        $input = $request->validate(['service_id' => ['required', 'string', 'max:100'], 'area_sqm' => ['required', 'integer', 'min:1'], 'room_option_id' => ['required', 'string', 'max:100'], 'cleaning_option_id' => ['required', 'string', 'max:100'], 'extra_option_ids' => ['sometimes', 'array'], 'extra_option_ids.*' => ['string', 'max:100']]);
        $quote = $action->execute($user, $input);

        return response()->json(['data' => $this->quoteResponse($quote)]);
    }

    public function createOrder(Request $request, CreateOrderFromQuote $action): JsonResponse
    {
        $user = $this->ensureClient($request);
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $key)) {
            return response()->json(['message' => 'The Idempotency-Key header must be a UUID v4.', 'code' => 'validation_error', 'errors' => ['Idempotency-Key' => ['A UUID v4 is required.']]], 422);
        }
        $input = $request->validate(['quote_id' => ['required', 'string'], 'scheduled_at' => ['required', 'date', 'after_or_equal:now', 'before_or_equal:'.now()->addYear()->toDateTimeString()], 'payment_method' => ['nullable'], 'address' => ['required', 'array'], 'address.full_address' => ['required', 'string', 'max:255'], 'address.fias_id' => ['nullable', 'string', 'max:255', 'required_without_all:address.latitude,address.longitude'], 'address.latitude' => ['nullable', 'numeric', 'required_with:address.longitude'], 'address.longitude' => ['nullable', 'numeric', 'required_with:address.latitude'], 'address.entrance' => ['nullable', 'string', 'max:50'], 'address.floor' => ['nullable', 'string', 'max:50'], 'address.apartment' => ['nullable', 'string', 'max:50'], 'address.intercom' => ['nullable', 'string', 'max:50'], 'address.comment' => ['nullable', 'string', 'max:2000']]);
        $order = $action->execute($user, $key, $input);

        return response()->json(['data' => $this->orderResponse($order)], $order->wasRecentlyCreated ? 201 : 200);
    }

    public function homeSummary(Request $request): JsonResponse
    {
        $user = $this->ensureClient($request);
        $orders = CleaningOrder::query()->where('client_id', $user->id)->whereIn('status', ['new', 'awaiting_cleaner', 'assigned', 'in_progress', 'pending', 'accepted'])->orderBy('scheduled_at')->get();
        $order = $orders->first();
        $labels = ['new' => 'Новая', 'awaiting_cleaner' => 'Ожидание клинера', 'assigned' => 'Клинер назначен', 'in_progress' => 'В процессе', 'pending' => 'Ожидание клинера', 'accepted' => 'Клинер назначен'];

        return response()->json(['data' => ['active_orders_count' => $orders->count(), 'active_order_status' => $order?->status?->value, 'active_order_status_label' => $order === null ? null : $labels[$order->status->value]]]);
    }

    private function ensureClient(Request $request): User
    {
        abort_unless($request->user()->role === UserRole::Client, 403);

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

    private function quoteResponse(ServiceQuote $quote): array
    {
        return ['quote_id' => $quote->id, 'service_id' => $quote->configuration['service_id'], 'currency' => $quote->currency, 'area_sqm' => $quote->configuration['area_sqm'], 'line_items' => $quote->line_items, 'subtotal' => array_sum(array_column($quote->line_items, 'amount')), 'discount' => 0, 'total_price' => $quote->total_price, 'expires_at' => $quote->expires_at];
    }

    private function orderResponse(CleaningOrder $order): array
    {
        return ['id' => $order->public_id, 'status' => $order->status->value, 'status_label' => 'Ожидание клинера', 'scheduled_at' => $order->scheduled_at, 'total_price' => $order->total_price, 'currency' => $order->currency, 'service' => $order->service_snapshot, 'created_at' => $order->created_at];
    }
}

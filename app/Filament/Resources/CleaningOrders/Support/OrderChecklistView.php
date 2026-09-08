<?php

namespace App\Filament\Resources\CleaningOrders\Support;

use App\Enums\ChecklistZone;
use App\Models\CleaningOrder;
use App\Models\OrderChecklistItem;
use App\Models\OrderExtraChecklistItem;
use App\Models\OrderLineItem;
use App\Models\User;

final class OrderChecklistView
{
    /** @return array{completed: int, total: int, remaining: int, percent: int} */
    public static function metrics(CleaningOrder $order): array
    {
        $items = collect(ChecklistZone::cases())
            ->flatMap(fn (ChecklistZone $zone): array => self::zone($order, $zone))
            ->concat(self::extras($order));
        $total = $items->count();
        $completed = $items->where('completed', true)->count();

        return [
            'completed' => $completed,
            'total' => $total,
            'remaining' => $total - $completed,
            'percent' => $total === 0 ? 0 : (int) round(($completed / $total) * 100),
        ];
    }

    /** @return array<int, array{label: string, completed: int, total: int, percent: int}> */
    public static function breakdown(CleaningOrder $order): array
    {
        $sections = collect(ChecklistZone::cases())->map(function (ChecklistZone $zone) use ($order): array {
            return self::section($zone->getLabel(), self::zone($order, $zone));
        });
        $sections->push(self::section('Дополнительные работы', self::extras($order)));

        return $sections->where('total', '>', 0)->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    public static function zone(CleaningOrder $order, ChecklistZone $zone): array
    {
        self::load($order);
        $completions = $order->checklistItems->keyBy('service_checklist_item_id');

        return $order->service?->checklistItems
            ->where('zone', $zone)
            ->map(fn ($item): array => self::item(
                title: $item->title,
                completion: $completions->get($item->id),
            ))
            ->values()
            ->all() ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    public static function extras(CleaningOrder $order): array
    {
        self::load($order);

        return $order->lineItems
            ->where('kind', 'extra_option')
            ->map(fn (OrderLineItem $item): array => self::item(
                title: $item->title,
                completion: $item->extraChecklistItem,
            ))
            ->values()
            ->all();
    }

    private static function load(CleaningOrder $order): void
    {
        $order->loadMissing([
            'service.checklistItems',
            'checklistItems.completedBy.cleanerProfile',
            'lineItems.extraChecklistItem.completedBy.cleanerProfile',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{label: string, completed: int, total: int, percent: int}
     */
    private static function section(string $label, array $items): array
    {
        $total = count($items);
        $completed = collect($items)->where('completed', true)->count();

        return [
            'label' => $label,
            'completed' => $completed,
            'total' => $total,
            'percent' => $total === 0 ? 0 : (int) round(($completed / $total) * 100),
        ];
    }

    /** @return array<string, mixed> */
    private static function item(string $title, OrderChecklistItem|OrderExtraChecklistItem|null $completion): array
    {
        $completed = $completion?->completed_at !== null;

        return [
            'title' => $title,
            'completed' => $completed,
            'status' => $completed ? 'Выполнено' : 'Ожидает выполнения',
            'completed_by' => self::completedBy($completion?->completedBy),
            'completed_at' => $completion?->completed_at,
        ];
    }

    private static function completedBy(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $name = $user->cleanerProfile?->name ?: $user->name ?: $user->phone;

        return filled($name) ? "{$name} · {$user->role->getLabel()}" : $user->role->getLabel();
    }
}

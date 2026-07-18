<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['public_id', 'client_id', 'cleaning_service_id', 'idempotency_key', 'request_hash', 'status', 'address', 'scheduled_at', 'comment', 'total_price', 'currency', 'service_snapshot'])]
class CleaningOrder extends Model
{
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'scheduled_at' => 'datetime',
            'total_price' => 'integer', 'service_snapshot' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function cleaners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cleaning_order_cleaners', 'cleaning_order_id', 'cleaner_id')
            ->withPivot(['accepted_at', 'started_at', 'completed_at'])
            ->withTimestamps();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(CleaningService::class, 'cleaning_service_id');
    }

    public function addressSnapshot(): HasOne
    {
        return $this->hasOne(OrderAddress::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(OrderLineItem::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function cleanerEarnings(int $cleanerCount): int
    {
        $teamEarnings = $this->relationLoaded('lineItems')
            ? $this->lineItems->sum(fn (OrderLineItem $item): int => $item->cleaner_earnings ?? 0)
            : $this->lineItems()->sum('cleaner_earnings');

        return (int) round($teamEarnings / max(1, $cleanerCount), 0, PHP_ROUND_HALF_UP);
    }
}

<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['public_id', 'client_id', 'cleaner_id', 'cleaning_service_id', 'service_quote_id', 'idempotency_key', 'status', 'address', 'scheduled_at', 'comment', 'total_price', 'currency', 'service_snapshot'])]
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

    public function cleaner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleaner_id');
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
}

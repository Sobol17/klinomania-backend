<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cleaning_order_id', 'provider', 'external_order_id', 'provider_payment_id', 'amount', 'currency', 'payment_url', 'expires_at', 'status', 'provider_status', 'error_code', 'error_message', 'confirmed_at'])]
class PaymentAttempt extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CleaningOrder::class, 'cleaning_order_id');
    }
}

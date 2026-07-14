<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'cleaning_service_id', 'configuration', 'line_items', 'service_snapshot', 'total_price', 'currency', 'expires_at', 'used_at'])]
class ServiceQuote extends Model
{
    use HasUlids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return ['configuration' => 'array', 'line_items' => 'array', 'service_snapshot' => 'array', 'expires_at' => 'datetime', 'used_at' => 'datetime', 'total_price' => 'integer'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(CleaningService::class, 'cleaning_service_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

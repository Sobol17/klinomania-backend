<?php

namespace App\Models;

use App\Enums\ChecklistZone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['cleaning_service_id', 'zone', 'title', 'sort_order'])]
class ServiceChecklistItem extends Model
{
    protected function casts(): array
    {
        return ['zone' => ChecklistZone::class, 'sort_order' => 'integer'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(CleaningService::class, 'cleaning_service_id');
    }

    public function orderChecklistItems(): HasMany
    {
        return $this->hasMany(OrderChecklistItem::class);
    }
}

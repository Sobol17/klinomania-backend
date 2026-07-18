<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderChecklistItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CleaningOrder::class, 'cleaning_order_id');
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(ServiceChecklistItem::class, 'service_checklist_item_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}

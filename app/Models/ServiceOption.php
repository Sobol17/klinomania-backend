<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['cleaning_service_id', 'code', 'group', 'title', 'subtitle', 'is_addon', 'is_default', 'price_modifier', 'sort_order', 'is_active'])]
class ServiceOption extends Model
{
    protected function casts(): array
    {
        return ['is_addon' => 'boolean', 'is_default' => 'boolean', 'is_active' => 'boolean', 'price_modifier' => 'integer'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(CleaningService::class, 'cleaning_service_id');
    }

    public function allowedWith(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'service_option_dependencies', 'service_option_id', 'allowed_with_option_id');
    }
}

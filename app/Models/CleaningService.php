<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'subtitle', 'short_description', 'long_description', 'cleaners_label', 'required_cleaners', 'duration_label', 'image_url', 'gallery', 'base_price', 'cleaner_base_earnings', 'price_per_sqm', 'min_area', 'max_area', 'area_step', 'min_price', 'currency', 'sort_order', 'is_active'])]
class CleaningService extends Model
{
    protected function casts(): array
    {
        return [
            'base_price' => 'integer', 'cleaner_base_earnings' => 'integer',
            'is_active' => 'boolean', 'gallery' => 'array', 'required_cleaners' => 'integer', 'price_per_sqm' => 'integer', 'min_area' => 'integer', 'max_area' => 'integer', 'area_step' => 'integer', 'min_price' => 'integer', 'sort_order' => 'integer',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(ServiceOption::class);
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(ServiceChecklistItem::class)->orderBy('sort_order');
    }
}

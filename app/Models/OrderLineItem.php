<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['kind', 'source_option_id', 'title', 'amount', 'cleaner_earnings'])]
class OrderLineItem extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'integer', 'cleaner_earnings' => 'integer'];
    }
}

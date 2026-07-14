<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['kind', 'source_option_id', 'title', 'amount'])]
class OrderLineItem extends Model {}

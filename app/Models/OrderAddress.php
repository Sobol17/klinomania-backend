<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['full_address', 'fias_id', 'latitude', 'longitude', 'entrance', 'floor', 'apartment', 'intercom', 'comment'])]
class OrderAddress extends Model {}

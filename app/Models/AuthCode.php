<?php

namespace App\Models;

use App\Enums\AuthCodePurpose;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['phone', 'code_hash', 'purpose', 'expires_at', 'consumed_at', 'attempts'])]
class AuthCode extends Model
{
    protected function casts(): array
    {
        return [
            'purpose' => AuthCodePurpose::class,
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}

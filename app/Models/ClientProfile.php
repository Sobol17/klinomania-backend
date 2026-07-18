<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'name', 'address', 'push_notifications_enabled', 'fcm_token', 'email_marketing_enabled'])]
class ClientProfile extends Model
{
    protected function casts(): array
    {
        return [
            'push_notifications_enabled' => 'boolean',
            'email_marketing_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

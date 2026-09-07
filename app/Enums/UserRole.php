<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    case Client = 'client';
    case Cleaner = 'cleaner';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::Client => 'Клиент',
            self::Cleaner => 'Клинер',
            self::Admin => 'Администратор',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Client => 'info',
            self::Cleaner => 'success',
            self::Admin => 'warning',
        };
    }
}

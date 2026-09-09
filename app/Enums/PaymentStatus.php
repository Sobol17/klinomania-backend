<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasLabel
{
    case Creating = 'creating';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Creating => 'Создаётся',
            self::Pending => 'Ожидает оплаты',
            self::Confirmed => 'Подтверждена',
            self::Failed => 'Ошибка',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Creating => 'gray',
            self::Pending => 'warning',
            self::Confirmed => 'success',
            self::Failed => 'danger',
        };
    }
}

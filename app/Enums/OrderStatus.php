<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case Processing = 'processing';
    case Confirmed = 'confirmed';
    case TeamFormed = 'team_formed';
    case InProgress = 'in_progress';
    case AwaitingPayment = 'awaiting_payment';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Processing => 'В обработке',
            self::Confirmed => 'Подтверждена',
            self::TeamFormed => 'Команда сформирована',
            self::InProgress => 'В работе',
            self::AwaitingPayment => 'Ожидает оплаты',
            self::Completed => 'Выполнена',
            self::Cancelled => 'Отменена',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Processing => 'gray',
            self::Confirmed => 'info',
            self::TeamFormed => 'primary',
            self::InProgress => 'warning',
            self::AwaitingPayment => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function allowsComplaint(): bool
    {
        return in_array($this, [self::InProgress, self::AwaitingPayment, self::Completed], true);
    }
}

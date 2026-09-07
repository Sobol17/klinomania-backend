<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ChecklistZone: string implements HasColor, HasLabel
{
    case Everywhere = 'all';
    case Rooms = 'rooms';
    case Kitchen = 'kitchen';
    case Bathroom = 'bathroom';

    public function label(): string
    {
        return $this->getLabel();
    }

    public function getColor(): string
    {
        return 'gray';
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Everywhere => 'Везде',
            self::Rooms => 'Комнаты, гардеробная и прихожая',
            self::Kitchen => 'Кухня',
            self::Bathroom => 'Санузел',
        };
    }
}

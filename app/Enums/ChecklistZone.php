<?php

namespace App\Enums;

enum ChecklistZone: string
{
    case Everywhere = 'all';
    case Rooms = 'rooms';
    case Kitchen = 'kitchen';
    case Bathroom = 'bathroom';

    public function label(): string
    {
        return match ($this) {
            self::Everywhere => 'Везде',
            self::Rooms => 'Комнаты, гардеробная и прихожая',
            self::Kitchen => 'Кухня',
            self::Bathroom => 'Санузел',
        };
    }
}

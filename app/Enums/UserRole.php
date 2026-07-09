<?php

namespace App\Enums;

enum UserRole: string
{
    case Client = 'client';
    case Cleaner = 'cleaner';
    case Admin = 'admin';
}

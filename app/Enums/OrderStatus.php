<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Processing = 'processing';
    case Confirmed = 'confirmed';
    case TeamFormed = 'team_formed';
    case InProgress = 'in_progress';
    case AwaitingPayment = 'awaiting_payment';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}

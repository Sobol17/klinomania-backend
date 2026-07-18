<?php

namespace App\Modules\Notifications\Events;

use App\Enums\OrderStatus;

class OrderStatusChanged
{
    public function __construct(
        public readonly int $orderId,
        public readonly OrderStatus $status,
    ) {}
}

<?php

namespace App\Modules\Notifications\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class OrderCreated implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly int $orderId) {}
}

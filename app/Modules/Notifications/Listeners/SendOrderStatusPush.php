<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Events\OrderStatusChanged;
use App\Modules\Notifications\Services\OrderStatusPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendOrderStatusPush implements ShouldQueue
{
    use InteractsWithQueue;

    public bool $afterCommit = true;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(private readonly OrderStatusPushService $pushService) {}

    public function handle(OrderStatusChanged $event): void
    {
        $this->pushService->send($event->orderId, $event->status);
    }
}

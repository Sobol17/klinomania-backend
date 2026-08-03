<?php

namespace App\Modules\Notifications\Listeners;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Notifications\Events\OrderCreated;
use App\Modules\Notifications\Notifications\NewOrderCreatedNotification;
use Illuminate\Support\Facades\Notification;

class NotifyAdminsAboutNewOrder
{
    public function handle(OrderCreated $event): void
    {
        $admins = User::query()
            ->where('role', UserRole::Admin)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();

        Notification::send($admins, new NewOrderCreatedNotification($event->orderId));
    }
}

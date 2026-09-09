<?php

namespace App\Modules\Notifications\Listeners;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Complaints\Events\ComplaintCreated;
use App\Modules\Notifications\Notifications\NewComplaintNotification;
use Illuminate\Support\Facades\Notification;

class NotifyAdminsAboutNewComplaint
{
    public function handle(ComplaintCreated $event): void
    {
        $admins = User::query()
            ->where('role', UserRole::Admin)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();

        Notification::send($admins, new NewComplaintNotification($event->complaintId));
    }
}

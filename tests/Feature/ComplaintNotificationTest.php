<?php

use App\Enums\UserRole;
use App\Models\Complaint;
use App\Models\User;
use App\Modules\Complaints\Events\ComplaintCreated;
use App\Modules\Notifications\Listeners\NotifyAdminsAboutNewComplaint;
use App\Modules\Notifications\Notifications\NewComplaintNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('new complaint listener emails administrators only', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $withoutEmail = User::factory()->create(['role' => UserRole::Admin, 'email' => null]);
    $client = User::factory()->create(['role' => UserRole::Client]);

    app(NotifyAdminsAboutNewComplaint::class)->handle(new ComplaintCreated(123));

    Notification::assertSentTo($admin, NewComplaintNotification::class);
    Notification::assertNotSentTo($withoutEmail, NewComplaintNotification::class);
    Notification::assertNotSentTo($client, NewComplaintNotification::class);
});

test('new complaint email contains details and admin link', function () {
    config(['app.url' => 'https://api.klinomania.test']);
    URL::forceRootUrl('https://api.klinomania.test');
    URL::forceScheme('https');
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $complaint = Complaint::factory()->create(['subject' => 'Повреждение', 'message' => 'Поцарапан стол']);
    $notification = new NewComplaintNotification($complaint->id);

    $mail = $notification->toMail($admin);

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->afterCommit)->toBeTrue()
        ->and($notification->tries)->toBe(3)
        ->and($notification->backoff)->toBe([60, 300])
        ->and($mail->subject)->toBe("Новая жалоба по заявке №{$complaint->order->public_id}")
        ->and($mail->introLines)->toContain('**Тема:** Повреждение', '**Сообщение:** Поцарапан стол')
        ->and($mail->actionText)->toBe('Открыть жалобу')
        ->and($mail->actionUrl)->toBe("https://api.klinomania.test/admin/complaints/{$complaint->id}");
});

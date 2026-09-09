<?php

use App\Enums\ComplaintStatus;
use App\Enums\UserRole;
use App\Models\Complaint;
use App\Models\User;
use App\Modules\Complaints\Actions\ChangeComplaintStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('administrator moves complaint through workflow', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $complaint = Complaint::factory()->create();
    $action = app(ChangeComplaintStatus::class);

    $inProgress = $action->execute($complaint, ComplaintStatus::InProgress, $admin, null);
    expect($inProgress->status)->toBe(ComplaintStatus::InProgress)
        ->and($inProgress->resolved_at)->toBeNull()
        ->and($inProgress->resolved_by)->toBeNull();

    $resolved = $action->execute($complaint, ComplaintStatus::Resolved, $admin, 'Вернули стоимость');
    expect($resolved->status)->toBe(ComplaintStatus::Resolved)
        ->and($resolved->admin_comment)->toBe('Вернули стоимость')
        ->and($resolved->resolved_at)->not->toBeNull()
        ->and($resolved->resolved_by)->toBe($admin->id);
});

test('resolution and rejection require an administrator comment', function (ComplaintStatus $status) {
    app(ChangeComplaintStatus::class)->execute(
        Complaint::factory()->create(),
        $status,
        User::factory()->create(['role' => UserRole::Admin]),
        null,
    );
})->with([ComplaintStatus::Resolved, ComplaintStatus::Rejected])
    ->throws(ValidationException::class);

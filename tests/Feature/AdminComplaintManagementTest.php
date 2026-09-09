<?php

use App\Enums\ComplaintStatus;
use App\Enums\UserRole;
use App\Filament\Resources\CleaningOrders\Pages\ViewCleaningOrder;
use App\Filament\Resources\CleaningOrders\RelationManagers\ComplaintsRelationManager;
use App\Filament\Resources\Complaints\Pages\ListComplaints;
use App\Filament\Resources\Complaints\Pages\ViewComplaint;
use App\Models\Complaint;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($this->admin);
});

test('complaint list and order relation display complaints', function () {
    $complaint = Complaint::factory()->create(['subject' => 'Повреждение мебели']);

    Livewire::test(ListComplaints::class)
        ->assertCanSeeTableRecords([$complaint])
        ->searchTable('Повреждение')
        ->assertCanSeeTableRecords([$complaint]);

    Livewire::test(ComplaintsRelationManager::class, [
        'ownerRecord' => $complaint->order,
        'pageClass' => ViewCleaningOrder::class,
    ])->assertCanSeeTableRecords([$complaint]);
});

test('complaint page actions use the complaint workflow', function () {
    $complaint = Complaint::factory()->create();
    $page = Livewire::test(ViewComplaint::class, ['record' => $complaint->getKey()]);

    $page->callAction('take')->assertNotified('Статус жалобы обновлён');
    expect($complaint->refresh()->status)->toBe(ComplaintStatus::InProgress);

    $page->callAction('resolve', data: ['admin_comment' => 'Компенсация согласована'])
        ->assertHasNoFormErrors()
        ->assertNotified('Статус жалобы обновлён');

    expect($complaint->refresh()->status)->toBe(ComplaintStatus::Resolved)
        ->and($complaint->admin_comment)->toBe('Компенсация согласована')
        ->and($complaint->resolved_by)->toBe($this->admin->id)
        ->and($complaint->resolved_at)->not->toBeNull();
});

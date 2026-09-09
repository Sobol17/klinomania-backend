<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Filament\Resources\PaymentAttempts\Pages\ListPaymentAttempts;
use App\Filament\Resources\PaymentAttempts\Pages\ViewPaymentAttempt;
use App\Filament\Resources\PaymentAttempts\PaymentAttemptResource;
use App\Models\CleaningOrder;
use App\Models\CleaningService;
use App\Models\PaymentAttempt;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($this->admin);
    $this->paymentClient = User::factory()->create([
        'role' => UserRole::Client,
        'name' => 'Анна Плательщик',
        'phone' => '+79990007766',
    ]);
    $service = CleaningService::query()->create([
        'name' => 'Уборка',
        'slug' => 'payment-admin-test',
        'base_price' => 7700,
    ]);
    $this->paymentOrder = CleaningOrder::query()->create([
        'public_id' => (string) Str::ulid(),
        'client_id' => $this->paymentClient->id,
        'cleaning_service_id' => $service->id,
        'status' => OrderStatus::AwaitingPayment,
        'address' => 'Иркутск',
        'scheduled_at' => now(),
        'total_price' => 7700,
    ]);
});

test('administrator sees searchable and filterable payment register', function () {
    $failed = adminPaymentAttempt($this->paymentOrder, PaymentStatus::Failed, [
        'external_order_id' => 'pay_failed_001',
        'provider_payment_id' => 'provider_001',
        'error_code' => '7',
        'error_message' => 'Платёж отклонён',
        'created_at' => now()->subDay(),
    ]);
    $confirmed = adminPaymentAttempt($this->paymentOrder, PaymentStatus::Confirmed, [
        'external_order_id' => 'pay_confirmed_002',
        'provider_payment_id' => 'provider_002',
        'confirmed_at' => now(),
    ]);

    $table = Livewire::test(ListPaymentAttempts::class)
        ->assertCanSeeTableRecords([$failed, $confirmed])
        ->assertSee('Анна Плательщик')
        ->assertSee('7 700,00 ₽')
        ->assertSee('Ошибка')
        ->searchTable('pay_failed_001')
        ->assertCanSeeTableRecords([$failed])
        ->assertCanNotSeeTableRecords([$confirmed])
        ->searchTable($this->paymentOrder->public_id)
        ->assertCanSeeTableRecords([$failed, $confirmed])
        ->searchTable('Анна Плательщик')
        ->assertCanSeeTableRecords([$failed, $confirmed]);

    $table->filterTable('failed', true)
        ->assertCanSeeTableRecords([$failed])
        ->assertCanNotSeeTableRecords([$confirmed])
        ->resetTableFilters()
        ->filterTable('status', PaymentStatus::Confirmed->value)
        ->assertCanSeeTableRecords([$confirmed])
        ->assertCanNotSeeTableRecords([$failed]);
});

test('payment card masks payment URL and exposes diagnostics', function () {
    $payment = adminPaymentAttempt($this->paymentOrder, PaymentStatus::Failed, [
        'payment_url' => 'https://securepay.tinkoff.ru/secret-token',
        'error_code' => '1001',
        'error_message' => 'Платёж отклонён',
    ]);

    Livewire::test(ViewPaymentAttempt::class, ['record' => $payment->id])
        ->assertOk()
        ->assertSee('Создана и скрыта')
        ->assertSee('Платёж отклонён')
        ->assertSee($this->paymentOrder->public_id)
        ->assertDontSee('secret-token');
});

test('payment resource is read only including direct URLs', function () {
    $payment = adminPaymentAttempt($this->paymentOrder, PaymentStatus::Pending);

    expect(PaymentAttemptResource::canCreate())->toBeFalse()
        ->and(PaymentAttemptResource::canEdit($payment))->toBeFalse()
        ->and(PaymentAttemptResource::canDelete($payment))->toBeFalse()
        ->and(PaymentAttemptResource::canDeleteAny())->toBeFalse();

    $this->get('/admin/payment-attempts/create')->assertForbidden();
    $this->get("/admin/payment-attempts/{$payment->id}/edit")->assertForbidden();

    Livewire::test(ListPaymentAttempts::class)
        ->assertTableActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('edit', record: $payment)
        ->assertTableActionDoesNotExist('delete', record: $payment);
});

/** @param array<string, mixed> $attributes */
function adminPaymentAttempt(CleaningOrder $order, PaymentStatus $status, array $attributes = []): PaymentAttempt
{
    return PaymentAttempt::query()->create(array_merge([
        'cleaning_order_id' => $order->id,
        'provider' => 'tbank',
        'external_order_id' => 'pay_'.Str::ulid(),
        'amount' => 770000,
        'currency' => 'RUB',
        'status' => $status,
        'expires_at' => now()->addDay(),
    ], $attributes));
}

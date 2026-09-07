<?php

use App\Enums\UserRole;
use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('guests are redirected from panel pages to login', function () {
    foreach (['', '/users', '/cleaning-orders', '/cleaning-services/create', '/extra-services'] as $path) {
        $this->get('/admin'.$path)->assertRedirect('/admin/login');
    }
    $this->get('/admin/login')->assertOk()->assertSee('Клиномания');
});

test('admin login regenerates the session and respects the intended destination', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->get('/admin/users')->assertRedirect('/admin/login');
    $sessionId = session()->getId();

    Livewire::test(Login::class)->fillForm(['email' => $admin->email, 'password' => 'password'])
        ->call('authenticate')->assertHasNoFormErrors()->assertRedirect('/admin/users');

    $this->assertAuthenticatedAs($admin, 'web');
    expect(session()->getId())->not->toBe($sessionId);
    $response = $this->get('/admin')->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('valid client and cleaner credentials cannot create an admin session', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);
    $user->createToken('mobile');

    Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')->assertHasFormErrors(['email']);

    $this->assertGuest('web');
    expect($user->tokens()->count())->toBe(1);
})->with([UserRole::Client, UserRole::Cleaner]);

test('logout invalidates the session and prevents subsequent panel access', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin, 'web')->withSession(['private-marker' => 'secret']);
    $this->get('/admin')->assertOk();
    $sessionId = session()->getId();
    $token = session()->token();

    $this->post('/admin/logout')->assertRedirect('/admin/login');

    $this->assertGuest('web');
    expect(session()->getId())->not->toBe($sessionId)
        ->and(session()->token())->not->toBe($token)
        ->and(session()->has('private-marker'))->toBeFalse();
    $this->get('/admin')->assertRedirect('/admin/login');
});

test('five failed attempts block login for a minute without blocking another email', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $other = User::factory()->create(['role' => UserRole::Admin]);
    $page = Livewire::test(Login::class)->fillForm(['email' => $admin->email, 'password' => 'wrong']);
    for ($i = 0; $i < 5; $i++) {
        $page->call('authenticate')->assertHasFormErrors(['email']);
    }
    $page->fillForm(['password' => 'password'])->call('authenticate')->assertNotified();
    $this->assertGuest('web');

    Livewire::test(Login::class)->fillForm(['email' => $other->email, 'password' => 'password'])
        ->call('authenticate')->assertHasNoFormErrors();
    $this->assertAuthenticatedAs($other, 'web');
    Auth::guard('web')->logout();

    $this->travel(61)->seconds();
    Livewire::test(Login::class)->fillForm(['email' => $admin->email, 'password' => 'password'])
        ->call('authenticate')->assertHasNoFormErrors();
    $this->assertAuthenticatedAs($admin, 'web');
});

test('successful login clears previous failures', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $page = Livewire::test(Login::class)->fillForm(['email' => $admin->email, 'password' => 'wrong']);
    for ($i = 0; $i < 4; $i++) {
        $page->call('authenticate')->assertHasFormErrors(['email']);
    }
    $page->fillForm(['password' => 'password'])->call('authenticate')->assertHasNoFormErrors();
    $this->assertAuthenticatedAs($admin, 'web');
    Auth::guard('web')->logout();

    $page = Livewire::test(Login::class)->fillForm(['email' => $admin->email, 'password' => 'wrong']);
    for ($i = 0; $i < 4; $i++) {
        $page->call('authenticate')->assertHasFormErrors(['email']);
    }
    $page->fillForm(['password' => 'password'])->call('authenticate')->assertHasNoFormErrors();
    $this->assertAuthenticatedAs($admin, 'web');
});

test('email case cannot bypass the limiter and another IP has its own allowance', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $page = Livewire::test(Login::class)->fillForm(['email' => strtoupper($admin->email), 'password' => 'wrong']);
    for ($i = 0; $i < 5; $i++) {
        $page->call('authenticate')->assertHasFormErrors(['email']);
    }
    $page->fillForm(['email' => $admin->email, 'password' => 'password'])
        ->call('authenticate')->assertNotified();
    $this->assertGuest('web');

    $otherIpPage = Livewire::test(Login::class)
        ->fillForm(['email' => $admin->email, 'password' => 'password']);
    // Livewire's test broker resets server variables on every component call.
    request()->server->set('REMOTE_ADDR', '192.0.2.10');
    expect($otherIpPage->instance()->authenticate())->not->toBeNull();
    $this->assertAuthenticatedAs($admin, 'web');
});

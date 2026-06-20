<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\Auth\Login;
use App\Models\Admin;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('redirects guests from the admin panel to login', function () {
    $this->get('/admin')->assertRedirect();
});

it('lets a verified admin reach the admin panel', function () {
    $this->actingAs(Admin::factory()->create(['status' => true]), 'admin');

    $this->get('/admin')->assertOk();
});

it('keeps a web-guard user out of the admin panel', function () {
    // Authenticated on the `web` guard, not `admin` — the admin panel treats
    // them as a guest and bounces them to its own login.
    $this->actingAs(User::factory()->create(['status' => true]), 'web');

    $this->get('/admin')->assertRedirect();
});

it('rejects a deactivated admin at the login form (correct password)', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = Admin::factory()->create(['status' => false]); // factory password = 'password'

    Livewire::test(Login::class)
        ->fillForm(['email' => $admin->email, 'password' => 'password'])
        ->call('authenticate');

    expect(auth('admin')->check())->toBeFalse();
});

it('lets an active admin authenticate at the login form', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = Admin::factory()->create(['status' => true]);

    Livewire::test(Login::class)
        ->fillForm(['email' => $admin->email, 'password' => 'password'])
        ->call('authenticate');

    expect(auth('admin')->check())->toBeTrue();
});

it('drops a deactivated admin from an existing session (per-request gate)', function () {
    // canAccessPanel() must consult status on every request, not just at login.
    $admin = Admin::factory()->create(['status' => true]);
    $this->actingAs($admin, 'admin');
    $this->get('/admin')->assertOk();

    $admin->update(['status' => false]);
    $this->get('/admin')->assertForbidden();
});

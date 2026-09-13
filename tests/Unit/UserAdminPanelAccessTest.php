<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\User;
use Filament\Panel;

it('User cannot access the admin panel regardless of status', function () {
    $user = User::factory()->make(['status' => true]);

    expect($user->canAccessPanel(Panel::make()->id('admin')))->toBeFalse();
});

it('User can access a non-admin panel only when active', function () {
    $active = User::factory()->make(['status' => true]);
    $inactive = User::factory()->make(['status' => false]);
    $panel = Panel::make()->id('app');

    expect($active->canAccessPanel($panel))->toBeTrue()
        ->and($inactive->canAccessPanel($panel))->toBeFalse();
});

it('User can never impersonate', function () {
    expect(User::factory()->make()->canImpersonate())->toBeFalse();
});

it('Admin can access every panel only when active, admin panel included', function () {
    $active = Admin::factory()->make(['status' => true]);
    $inactive = Admin::factory()->make(['status' => false]);

    expect($active->canAccessPanel(Panel::make()->id('admin')))->toBeTrue()
        ->and($active->canAccessPanel(Panel::make()->id('app')))->toBeTrue()
        ->and($inactive->canAccessPanel(Panel::make()->id('admin')))->toBeFalse();
});

it('Admin can always impersonate', function () {
    expect(Admin::factory()->make()->canImpersonate())->toBeTrue();
});

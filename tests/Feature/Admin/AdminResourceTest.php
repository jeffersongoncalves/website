<?php

use App\Filament\Admin\Resources\Admins\Pages\CreateAdmin;
use App\Filament\Admin\Resources\Admins\Pages\ListAdmins;
use App\Models\Admin;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(Admin::factory()->create(['status' => true]), 'admin');
});

it('lists admins in the admin panel', function () {
    $admins = Admin::factory()->count(2)->create(['status' => true]);

    Livewire::test(ListAdmins::class)
        ->assertOk()
        ->assertCanSeeTableRecords($admins);
});

it('creates an admin via the admin resource', function () {
    Livewire::test(CreateAdmin::class)
        ->fillForm([
            'status' => true,
            'name' => 'New Admin',
            'email' => 'new.admin@example.test',
            'password' => 'secret123',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Admin::query()->where('email', 'new.admin@example.test')->exists())->toBeTrue();
});

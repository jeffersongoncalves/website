<?php

declare(strict_types=1);

use App\Models\Admin;
use Filament\Facades\Filament;
use JeffersonGoncalves\Filament\Admin\Resources\Admins\AdminResource;
use JeffersonGoncalves\Filament\Admin\Resources\Admins\Pages\CreateAdmin;
use JeffersonGoncalves\Filament\Admin\Resources\Admins\Pages\ListAdmins;
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

it('builds a global search result url for an admin', function () {
    $admin = Admin::factory()->create(['status' => true]);

    expect(AdminResource::getGlobalSearchResultUrl($admin))
        ->toBe(AdminResource::getUrl('view', ['record' => $admin]))
        ->and(AdminResource::getGloballySearchableAttributes())
        ->toBe(['name', 'email']);
});

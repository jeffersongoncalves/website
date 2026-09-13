<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\Admin;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(Admin::factory()->create(['status' => true]), 'admin');
});

it('lists users in the admin panel', function () {
    $users = User::factory()->count(3)->create();

    Livewire::test(ListUsers::class)
        ->assertOk()
        ->assertCanSeeTableRecords($users);
});

it('creates a user via the admin resource', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'status' => true,
            'name' => 'New Person',
            'email' => 'new.person@example.test',
            'password' => 'secret123',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', 'new.person@example.test')->exists())->toBeTrue();
});

it('builds a global search result url for a user', function () {
    $user = User::factory()->create(['status' => true]);

    expect(UserResource::getGlobalSearchResultUrl($user))
        ->toBe(UserResource::getUrl('view', ['record' => $user]))
        ->and(UserResource::getGloballySearchableAttributes())
        ->toBe(['name', 'email']);
});

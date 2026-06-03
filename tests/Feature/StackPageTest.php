<?php

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Livewire\Site\StackPage;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('links a stack package to its catalogue page when it is published here', function () {
    Project::query()->create([
        'name' => 'Horizon',
        'slug' => 'laravel-horizon',
        'category' => ProjectCategory::LaravelPackage,
        'status' => ProjectStatus::Published,
        'packagist_url' => 'https://packagist.org/packages/laravel/horizon',
        'published_at' => now(),
    ]);

    Livewire::test(StackPage::class)
        // The internal catalogue link wins over the external Packagist link.
        ->assertSee(route('projects.show', ['slug' => 'laravel-horizon']), false)
        ->assertDontSee('https://packagist.org/packages/laravel/horizon');
});

it('falls back to the Packagist URL for a stack package not in the catalogue', function () {
    // Nothing seeded — laravel/tinker is not in the catalogue, so its chip
    // points straight at Packagist.
    Livewire::test(StackPage::class)
        ->assertSee('https://packagist.org/packages/laravel/tinker', false);
});

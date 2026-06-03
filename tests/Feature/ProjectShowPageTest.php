<?php

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function pluginProject(array $attrs = []): Project
{
    return Project::query()->create(array_merge([
        'name' => 'Filament Thing',
        'slug' => 'filament-thing',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/acme/filament-thing',
        'published_at' => now(),
    ], $attrs));
}

it('301s a project requested under the wrong section to its canonical section', function () {
    pluginProject(); // a Filament plugin is canonical under /projects/{slug}

    $this->get('/articles/filament-thing')
        ->assertStatus(301)
        ->assertRedirect('/projects/filament-thing');
});

it('merges consecutive versions that resolve to the same branch into one chip', function () {
    Storage::fake('github'); // README render writes to the github disk

    // v4 (2.x) and v5 (3.x) are both overridden onto `master`, so they collapse
    // into a single "v4/v5" chip; v3 (1.x) stays on its own.
    pluginProject([
        'versions' => ['v3', 'v4', 'v5'],
        'branch_overrides' => ['2.x' => 'master', '3.x' => 'master'],
    ]);

    $this->get('/projects/filament-thing')
        ->assertOk()
        ->assertSee('v4/v5')
        ->assertSee('v3');
});

it('renders a project page under its canonical section without redirecting', function () {
    Storage::fake('github');
    pluginProject();

    $this->get('/projects/filament-thing')
        ->assertOk()
        ->assertSee('Filament Thing');
});

<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Filament\Admin\Resources\Projects\Pages\ListProjects;
use App\Filament\Admin\Resources\Projects\ProjectResource;
use App\Models\Admin;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(Admin::factory()->create(['status' => true]), 'admin');
});

it('renders the projects list in the admin panel', function () {
    $project = createProject([
        'slug' => 'a-tool',
        'name' => 'A Tool',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
    ]);

    Livewire::test(ListProjects::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$project]);
});

it('builds a global search result url for a project', function () {
    $project = createProject([
        'slug' => 'searchable-tool',
        'name' => 'Searchable Tool',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
    ]);

    expect(ProjectResource::getGlobalSearchResultUrl($project))
        ->toBe(ProjectResource::getUrl('view', ['record' => $project]))
        ->and(ProjectResource::getGloballySearchableAttributes())
        ->toBe(['name', 'slug', 'repo']);
});

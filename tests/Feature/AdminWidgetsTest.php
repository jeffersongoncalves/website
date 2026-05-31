<?php

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Filament\Admin\Widgets\ProjectImportsWidget;
use App\Filament\Admin\Widgets\ProjectsPerDayChart;
use App\Models\Project;
use Livewire\Livewire;

function makeProjectAt(string $slug, \DateTimeInterface $createdAt, bool $starred = false): Project
{
    $project = Project::query()->create([
        'slug' => $slug,
        'name' => $slug,
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'starred_at' => $starred ? $createdAt : null,
    ]);

    // created_at is set by timestamps on insert — force it to the target day.
    $project->forceFill(['created_at' => $createdAt])->saveQuietly();

    return $project;
}

it('renders the project imports widget with the weekly count and star total', function (): void {
    makeProjectAt('a', now());
    makeProjectAt('b', now(), starred: true);
    makeProjectAt('c', now()->subWeek()); // last week

    Livewire::test(ProjectImportsWidget::class)
        ->assertOk()
        ->assertSee(__('admin.widgets.project_imports.new_week'))
        ->assertSee(__('admin.widgets.project_imports.starred_total'));
});

it('renders the projects-per-day chart', function (): void {
    makeProjectAt('a', now());
    makeProjectAt('b', now()->subDays(3), starred: true);

    Livewire::test(ProjectsPerDayChart::class)->assertOk();
});

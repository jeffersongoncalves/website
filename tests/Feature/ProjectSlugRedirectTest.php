<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectSlugAlias;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function aliasProject(string $slug, ProjectStatus $status = ProjectStatus::Published): Project
{
    return Project::query()->create([
        'name' => 'D3',
        'slug' => $slug,
        'category' => ProjectCategory::JavascriptPackage,
        'status' => $status,
        'github_url' => 'https://github.com/mbostock/d3',
        'published_at' => now(),
    ]);
}

it('301-redirects a retired slug to the current slug', function () {
    $project = aliasProject('mbostock-d3');
    ProjectSlugAlias::query()->create(['project_id' => $project->id, 'slug' => 'd3']);

    $this->get('/pt_BR/projects/d3')
        ->assertStatus(301)
        ->assertRedirect(route('projects.show', ['slug' => 'mbostock-d3']));
});

it('preserves the readme-version query string through the redirect', function () {
    $project = aliasProject('mbostock-d3');
    ProjectSlugAlias::query()->create(['project_id' => $project->id, 'slug' => 'd3']);

    $this->get('/pt_BR/projects/d3?v=2.x')
        ->assertStatus(301)
        ->assertRedirect(route('projects.show', ['slug' => 'mbostock-d3']).'?v=2.x');
});

it('404s an unknown slug with no alias', function () {
    $this->get('/pt_BR/projects/does-not-exist')->assertNotFound();
});

it('404s when the aliased project is no longer published', function () {
    $project = aliasProject('mbostock-d3', ProjectStatus::Draft);
    ProjectSlugAlias::query()->create(['project_id' => $project->id, 'slug' => 'd3']);

    $this->get('/pt_BR/projects/d3')->assertNotFound();
});

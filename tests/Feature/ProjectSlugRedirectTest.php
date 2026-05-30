<?php

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

    $this->get('/projects/d3')
        ->assertStatus(301)
        ->assertRedirect('/projects/mbostock-d3');
});

it('preserves the readme-version query string through the redirect', function () {
    $project = aliasProject('mbostock-d3');
    ProjectSlugAlias::query()->create(['project_id' => $project->id, 'slug' => 'd3']);

    $this->get('/projects/d3?v=2.x')
        ->assertStatus(301)
        ->assertRedirect('/projects/mbostock-d3?v=2.x');
});

it('404s an unknown slug with no alias', function () {
    $this->get('/projects/does-not-exist')->assertNotFound();
});

it('404s when the aliased project is no longer published', function () {
    $project = aliasProject('mbostock-d3', ProjectStatus::Draft);
    ProjectSlugAlias::query()->create(['project_id' => $project->id, 'slug' => 'd3']);

    $this->get('/projects/d3')->assertNotFound();
});

it('renames an off-canonical slug and records an alias via the operation', function () {
    $project = aliasProject('d3'); // bare repo name, missing owner prefix

    $operation = require base_path('operations/2026_05_30_000000_rename_project_slugs_to_canonical.php');
    $operation->process();

    expect($project->fresh()->slug)->toBe('mbostock-d3');
    expect(ProjectSlugAlias::query()->where('slug', 'd3')->where('project_id', $project->id)->exists())->toBeTrue();

    // End to end: the old URL now redirects to the freshly-canonical slug.
    $this->get('/projects/d3')
        ->assertStatus(301)
        ->assertRedirect('/projects/mbostock-d3');
});

it('leaves an already-canonical slug untouched (idempotent)', function () {
    $project = aliasProject('mbostock-d3');

    $operation = require base_path('operations/2026_05_30_000000_rename_project_slugs_to_canonical.php');
    $operation->process();

    expect($project->fresh()->slug)->toBe('mbostock-d3');
    expect(ProjectSlugAlias::query()->count())->toBe(0);
});

it('skips a rename when the canonical slug is already taken', function () {
    aliasProject('mbostock-d3'); // occupies the canonical slug
    $dup = Project::query()->create([
        'name' => 'D3 dup',
        'slug' => 'd3',
        'category' => ProjectCategory::JavascriptPackage,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/mbostock/d3',
        'published_at' => now(),
    ]);

    $operation = require base_path('operations/2026_05_30_000000_rename_project_slugs_to_canonical.php');
    $operation->process();

    // The duplicate keeps its slug — no collision — and no alias is written.
    expect($dup->fresh()->slug)->toBe('d3');
    expect(ProjectSlugAlias::query()->where('slug', 'd3')->exists())->toBeFalse();
});

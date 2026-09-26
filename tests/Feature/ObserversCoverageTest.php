<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Admin;
use App\Models\Project;
use App\Models\ProjectSlugAlias;
use App\Models\User;
use App\Observers\ProjectObserver;
use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\InvalidArgumentException;

// ---------------------------------------------------------------------------
// UserObserver -> users_count cache invalidation
// ---------------------------------------------------------------------------

it('forgets the users_count cache when a User is created', function () {
    Cache::rememberForever('users_count', fn () => 0);
    expect(Cache::has('users_count'))->toBeTrue();

    User::factory()->create();

    expect(Cache::has('users_count'))->toBeFalse();
});

it('forgets the users_count cache when a User is deleted', function () {
    $user = User::factory()->create();
    Cache::rememberForever('users_count', fn () => User::query()->count());
    expect(Cache::get('users_count'))->toBe(1);

    $user->delete();

    expect(Cache::has('users_count'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// AdminObserver -> admins_count cache invalidation
// ---------------------------------------------------------------------------

it('forgets the admins_count cache when an Admin is created', function () {
    Cache::rememberForever('admins_count', fn () => 0);
    expect(Cache::has('admins_count'))->toBeTrue();

    Admin::factory()->create();

    expect(Cache::has('admins_count'))->toBeFalse();
});

it('forgets the admins_count cache when an Admin is deleted', function () {
    $admin = Admin::factory()->create();
    Cache::rememberForever('admins_count', fn () => Admin::query()->count());
    expect(Cache::get('admins_count'))->toBe(1);

    $admin->delete();

    expect(Cache::has('admins_count'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// ProjectObserver — restored/forceDeleted (Project has no SoftDeletes, so
// Eloquent never fires these for real; called directly since they're still
// real, reachable code paths a future SoftDeletes addition would trigger)
// and the flush() cache-exception swallow.
// ---------------------------------------------------------------------------

function observerCoverage_publishedProject(): Project
{
    return createProject([
        'slug' => 'observer-coverage',
        'name' => 'Observer Coverage',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'featured' => true,
    ]);
}

it('flush()es caches on ProjectObserver::restored', function () {
    $project = observerCoverage_publishedProject();
    Cache::rememberForever('featured_projects', fn () => 'stale');

    (new ProjectObserver)->restored($project);

    expect(Cache::has('featured_projects'))->toBeFalse();
});

it('flush()es caches on ProjectObserver::forceDeleted', function () {
    $project = observerCoverage_publishedProject();
    Cache::rememberForever('featured_projects', fn () => 'stale');

    (new ProjectObserver)->forceDeleted($project);

    expect(Cache::has('featured_projects'))->toBeFalse();
});

it('swallows an InvalidArgumentException from the cache store during ProjectObserver flush', function () {
    $project = observerCoverage_publishedProject();
    $exception = new class extends Exception implements InvalidArgumentException {};
    // Full replacement: stub every method this flow actually calls — `delete`
    // (throws, inside ProjectObserver::flush()'s try/catch) plus `add`/
    // `increment` (CachePublicPage::flush()'s own calls, unconditional,
    // running right after the try/catch regardless of the swallowed
    // exception).
    Cache::shouldReceive('delete')->once()->andThrow($exception);
    Cache::shouldReceive('add')->andReturn(true);
    Cache::shouldReceive('increment')->andReturn(1);

    (new ProjectObserver)->deleted($project);

    expect(true)->toBeTrue();
});

// ---------------------------------------------------------------------------
// ProjectSlugAlias model
// ---------------------------------------------------------------------------

it('persists a ProjectSlugAlias with its fillable columns', function () {
    $project = createProject([
        'name' => 'D3',
        'slug' => 'mbostock-d3',
        'category' => ProjectCategory::JavascriptPackage,
        'status' => ProjectStatus::Published,
    ]);

    $alias = ProjectSlugAlias::query()->create([
        'project_id' => $project->id,
        'slug' => 'd3',
    ]);

    expect($alias->slug)->toBe('d3')
        ->and($alias->project_id)->toBe($project->id);

    $this->assertDatabaseHas('project_slug_aliases', [
        'project_id' => $project->id,
        'slug' => 'd3',
    ]);
});

it('belongs to its Project through the project relationship', function () {
    $project = createProject([
        'name' => 'D3',
        'slug' => 'mbostock-d3',
        'category' => ProjectCategory::JavascriptPackage,
        'status' => ProjectStatus::Published,
    ]);
    $alias = ProjectSlugAlias::query()->create([
        'project_id' => $project->id,
        'slug' => 'd3',
    ]);

    expect($alias->project)->toBeInstanceOf(Project::class)
        ->and($alias->project->is($project))->toBeTrue();
});

it('is exposed via the Project slugAliases hasMany relationship', function () {
    $project = createProject([
        'name' => 'D3',
        'slug' => 'mbostock-d3',
        'category' => ProjectCategory::JavascriptPackage,
        'status' => ProjectStatus::Published,
    ]);
    ProjectSlugAlias::query()->create(['project_id' => $project->id, 'slug' => 'd3']);

    expect($project->slugAliases()->count())->toBe(1)
        ->and($project->slugAliases->first()->slug)->toBe('d3');
});

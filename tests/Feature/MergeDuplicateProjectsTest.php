<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<string, mixed>  $attrs
 */
function dupProject(array $attrs): Project
{
    $starredAt = $attrs['starred_at'] ?? null;
    $createdAt = $attrs['created_at'] ?? null;
    unset($attrs['starred_at'], $attrs['created_at']);

    $project = Project::query()->create(array_merge([
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
    ], $attrs));

    $force = array_filter([
        'starred_at' => $starredAt,
        'created_at' => $createdAt,
    ], fn ($v) => $v !== null);

    if ($force !== []) {
        $project->forceFill($force)->save();
    }

    return $project->refresh();
}

it('reports no duplicates when no github_repo_id is shared', function () {
    dupProject(['slug' => 'solo', 'name' => 'Solo', 'github_repo_id' => 1]);

    $this->artisan('projects:merge-duplicate-repos')
        ->expectsOutputToContain('No duplicate github_repo_id groups found.')
        ->assertSuccessful();

    expect(Project::query()->count())->toBe(1);
});

it('dry-run prints the merge plan but writes nothing', function () {
    Http::swap($f = new Factory);
    $f->preventStrayRequests();
    $f->fake(['api.github.com/repos/*' => Http::response(['html_url' => 'https://github.com/acme/widget'], 200)]);

    dupProject(['slug' => 'widget', 'name' => 'Widget', 'github_repo_id' => 42, 'github_url' => 'https://github.com/acme/widget', 'created_at' => now()->subDays(2)]);
    dupProject(['slug' => 'widget-old', 'name' => 'Widget Old', 'github_repo_id' => 42, 'github_url' => 'https://github.com/acme/widget-old', 'created_at' => now()->subDay()]);

    $this->artisan('projects:merge-duplicate-repos --dry-run')
        ->expectsOutputToContain('keep [widget]')
        ->expectsOutputToContain('Dry run — nothing was written. Re-run without --dry-run to apply.')
        ->assertSuccessful();

    expect(Project::query()->count())->toBe(2);
    $this->assertDatabaseMissing('project_slug_aliases', ['slug' => 'widget-old']);
});

it('merges duplicates: keeps the oldest row, freshest metrics, earliest starred_at, OR-ed featured, and aliases the loser slug', function () {
    Http::swap($f = new Factory);
    $f->preventStrayRequests();
    $f->fake(['api.github.com/repos/*' => Http::response(['html_url' => 'https://github.com/acme/widget'], 200)]);

    $keeper = dupProject([
        'slug' => 'widget', 'name' => 'Widget', 'github_repo_id' => 42,
        'github_url' => 'https://github.com/acme/widget',
        'created_at' => now()->subDays(2),
        'stars' => 5, 'last_synced_at' => now()->subDay(),
        'featured' => false,
        'starred_at' => now()->subDays(10),
    ]);
    $loser = dupProject([
        'slug' => 'widget-fork', 'name' => 'Widget Fork', 'github_repo_id' => 42,
        'github_url' => 'https://github.com/acme/widget-fork',
        'created_at' => now()->subDay(),
        'stars' => 50, 'last_synced_at' => now(),
        'featured' => true,
        'starred_at' => now()->subDays(20),
        'downloads_label' => '1k/month',
    ]);

    $this->artisan('projects:merge-duplicate-repos --no-interaction')
        ->expectsOutputToContain("merged 1 row(s) into #{$keeper->id}")
        ->assertSuccessful();

    expect(Project::query()->count())->toBe(1);

    $merged = $keeper->fresh();
    expect($merged->id)->toBe($keeper->id)
        ->and($merged->stars)->toBe(50)
        ->and($merged->featured)->toBeTrue()
        ->and($merged->starred_at->toDateString())->toBe(now()->subDays(20)->toDateString())
        ->and($merged->downloads_label)->toBe('1k/month');

    $this->assertDatabaseHas('project_slug_aliases', ['project_id' => $keeper->id, 'slug' => 'widget-fork']);
    expect(Project::query()->where('slug', 'widget-fork')->exists())->toBeFalse();

    unset($loser);
});

it('updates the keeper github_url to the re-verified canonical URL when it differs', function () {
    Http::swap($f = new Factory);
    $f->preventStrayRequests();
    // The stored URL is stale (repo renamed) — GitHub now reports a different html_url.
    $f->fake(['api.github.com/repos/*' => Http::response(['html_url' => 'https://github.com/acme/widget-renamed'], 200)]);

    $keeper = dupProject(['slug' => 'widget', 'name' => 'Widget', 'github_repo_id' => 42, 'github_url' => 'https://github.com/acme/widget', 'created_at' => now()->subDays(2)]);
    dupProject(['slug' => 'widget2', 'name' => 'Widget2', 'github_repo_id' => 42, 'github_url' => 'https://github.com/acme/widget2', 'created_at' => now()->subDay()]);

    $this->artisan('projects:merge-duplicate-repos --no-interaction')
        ->expectsOutputToContain('github_url: https://github.com/acme/widget -> https://github.com/acme/widget-renamed')
        ->assertSuccessful();

    expect($keeper->fresh()->github_url)->toBe('https://github.com/acme/widget-renamed');
});

it('keeps the existing github_url and warns when canonical re-verification hits a rate limit', function () {
    Http::swap($f = new Factory);
    $f->preventStrayRequests();
    $f->fake(['api.github.com/repos/*' => Http::response('', 403, [
        'X-RateLimit-Remaining' => '0',
        'X-RateLimit-Reset' => (string) (time() + 60),
    ])]);

    $keeper = dupProject(['slug' => 'widget', 'name' => 'Widget', 'github_repo_id' => 42, 'github_url' => 'https://github.com/acme/widget', 'created_at' => now()->subDays(2)]);
    dupProject(['slug' => 'widget2', 'name' => 'Widget2', 'github_repo_id' => 42, 'github_url' => 'https://github.com/acme/widget2', 'created_at' => now()->subDay()]);

    $this->artisan('projects:merge-duplicate-repos --no-interaction')
        ->expectsOutputToContain('could not re-verify canonical github_url (rate limited) — keeping current value')
        ->assertSuccessful();

    expect($keeper->fresh()->github_url)->toBe('https://github.com/acme/widget');
});

it('merges more than one duplicate group in a single run', function () {
    Http::swap($f = new Factory);
    $f->preventStrayRequests();
    $f->fake(['api.github.com/repos/*' => Http::response(['html_url' => null], 200)]);

    dupProject(['slug' => 'a1', 'name' => 'A1', 'github_repo_id' => 1, 'github_url' => 'https://github.com/acme/a', 'created_at' => now()->subDays(2)]);
    dupProject(['slug' => 'a2', 'name' => 'A2', 'github_repo_id' => 1, 'github_url' => 'https://github.com/acme/a2', 'created_at' => now()->subDay()]);
    dupProject(['slug' => 'b1', 'name' => 'B1', 'github_repo_id' => 2, 'github_url' => 'https://github.com/acme/b', 'created_at' => now()->subDays(2)]);
    dupProject(['slug' => 'b2', 'name' => 'B2', 'github_repo_id' => 2, 'github_url' => 'https://github.com/acme/b2', 'created_at' => now()->subDay()]);

    $this->artisan('projects:merge-duplicate-repos --no-interaction')->assertSuccessful();

    expect(Project::query()->count())->toBe(2);
});

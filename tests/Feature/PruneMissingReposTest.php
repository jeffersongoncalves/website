<?php

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Swap a fresh HTTP factory so our stubs win over the global GitHub fake
    // (which answers every api.github.com/repos/* with 200).
    Http::swap($factory = new Factory);
    $factory->preventStrayRequests();
    $factory->fake([
        'api.github.com/repos/acme/gone' => Http::response('', 404),
        'api.github.com/repos/acme/alive' => Http::response(['default_branch' => 'main'], 200),
    ]);
});

function repoProject(string $name, string $repo): Project
{
    return Project::query()->create([
        'slug' => $name,
        'name' => $name,
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'github_url' => "https://github.com/acme/{$repo}",
        'published_at' => now(),
    ]);
}

it('reports missing repos without deleting on a dry run', function () {
    repoProject('gone-pkg', 'gone');
    repoProject('alive-pkg', 'alive');

    $this->artisan('projects:prune-missing-repos')
        ->assertSuccessful();

    expect(Project::query()->where('slug', 'gone-pkg')->exists())->toBeTrue()
        ->and(Project::query()->where('slug', 'alive-pkg')->exists())->toBeTrue();
});

it('deletes only the 404 repo when --delete is given', function () {
    repoProject('gone-pkg', 'gone');
    repoProject('alive-pkg', 'alive');

    $this->artisan('projects:prune-missing-repos --delete --no-interaction')
        ->assertSuccessful();

    expect(Project::query()->where('slug', 'gone-pkg')->exists())->toBeFalse()
        ->and(Project::query()->where('slug', 'alive-pkg')->exists())->toBeTrue();
});

it('never deletes a paid/private project that 404s (e.g. flux-pro)', function () {
    Http::swap($factory = new Factory);
    $factory->preventStrayRequests();
    $factory->fake(['api.github.com/repos/livewire/flux-pro' => Http::response('', 404)]);

    Project::query()->create([
        'slug' => 'flux-pro',
        'name' => 'Flux Pro',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/livewire/flux-pro',
        'is_paid' => true,
        'published_at' => now(),
    ]);

    $this->artisan('projects:prune-missing-repos --delete --no-interaction')
        ->assertSuccessful();

    expect(Project::query()->where('slug', 'flux-pro')->exists())->toBeTrue();
});

it('keeps a 404 repo that is still published on packagist/npm (renamed, not gone)', function () {
    Http::swap($factory = new Factory);
    $factory->preventStrayRequests();
    $factory->fake(['api.github.com/repos/acme/renamed' => Http::response('', 404)]);

    Project::query()->create([
        'slug' => 'renamed-pkg',
        'name' => 'Renamed Pkg',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/acme/renamed',
        'packagist_url' => 'https://packagist.org/packages/acme/renamed',
        'published_at' => now(),
    ]);

    $this->artisan('projects:prune-missing-repos --delete --no-interaction')
        ->assertSuccessful();

    expect(Project::query()->where('slug', 'renamed-pkg')->exists())->toBeTrue();
});

it('never deletes a repo it could not verify (5xx = unknown)', function () {
    Http::swap($factory = new Factory);
    $factory->preventStrayRequests();
    $factory->fake(['api.github.com/repos/acme/flaky' => Http::response('', 503)]);

    repoProject('flaky-pkg', 'flaky');

    $this->artisan('projects:prune-missing-repos --delete --no-interaction')
        ->assertSuccessful();

    expect(Project::query()->where('slug', 'flaky-pkg')->exists())->toBeTrue();
});

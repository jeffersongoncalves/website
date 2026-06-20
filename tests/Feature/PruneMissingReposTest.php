<?php

declare(strict_types=1);

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

/**
 * @param  array<string, mixed>  $attrs
 */
function repoProject(string $name, string $repoSlug, array $attrs = []): Project
{
    return Project::query()->create(array_merge([
        'slug' => $name,
        'name' => $name,
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'github_url' => "https://github.com/{$repoSlug}",
        'stars' => 0,
        'downloads' => 0,
        'published_at' => now(),
    ], $attrs));
}

it('reports missing repos without deleting on a dry run', function () {
    repoProject('gone-pkg', 'acme/gone');
    repoProject('alive-pkg', 'acme/alive');

    $this->artisan('projects:prune-missing-repos')->assertSuccessful();

    expect(Project::query()->where('slug', 'gone-pkg')->exists())->toBeTrue()
        ->and(Project::query()->where('slug', 'alive-pkg')->exists())->toBeTrue();
});

it('deletes the zero-metric 404 repo but keeps the one that still resolves', function () {
    repoProject('gone-pkg', 'acme/gone');
    repoProject('alive-pkg', 'acme/alive');

    $this->artisan('projects:prune-missing-repos --delete --no-interaction')->assertSuccessful();

    expect(Project::query()->where('slug', 'gone-pkg')->exists())->toBeFalse()
        ->and(Project::query()->where('slug', 'alive-pkg')->exists())->toBeTrue();
});

it('deletes without a prompt when --force is given', function () {
    repoProject('gone-pkg', 'acme/gone');

    // No --no-interaction here: --force must bypass the confirmation itself.
    $this->artisan('projects:prune-missing-repos --delete --force')->assertSuccessful();

    expect(Project::query()->where('slug', 'gone-pkg')->exists())->toBeFalse();
});

it('deletes a 404 repo even when it still carries packagist + npm links', function () {
    // The production dead repos (beyondcode/*) keep stale registry links — the
    // 404 is what matters, not the links.
    repoProject('gone-pkg', 'acme/gone', [
        'packagist_url' => 'https://packagist.org/packages/acme/gone',
        'npm_url' => 'https://www.npmjs.com/package/gone',
    ]);

    $this->artisan('projects:prune-missing-repos --delete --no-interaction')->assertSuccessful();

    expect(Project::query()->where('slug', 'gone-pkg')->exists())->toBeFalse();
});

it('never deletes a paid/private project that 404s (e.g. flux-pro)', function () {
    Http::swap($factory = new Factory);
    $factory->preventStrayRequests();
    $factory->fake(['api.github.com/repos/livewire/flux-pro' => Http::response('', 404)]);

    repoProject('flux-pro', 'livewire/flux-pro', ['is_paid' => true]);

    $this->artisan('projects:prune-missing-repos --delete --no-interaction')->assertSuccessful();

    expect(Project::query()->where('slug', 'flux-pro')->exists())->toBeTrue();
});

it('never scans a repo that still has metrics (stars > 0), even if it 404s', function () {
    Http::swap($factory = new Factory);
    $factory->preventStrayRequests();
    $factory->fake(['api.github.com/repos/acme/popular' => Http::response('', 404)]);

    repoProject('popular-pkg', 'acme/popular', ['stars' => 1200]);

    $this->artisan('projects:prune-missing-repos --delete --no-interaction')->assertSuccessful();

    expect(Project::query()->where('slug', 'popular-pkg')->exists())->toBeTrue();
});

it('ignores website/youtube/article categories even with a 404 github_url', function () {
    Http::swap($factory = new Factory);
    $factory->preventStrayRequests();
    $factory->fake(['api.github.com/repos/acme/post' => Http::response('', 404)]);

    repoProject('an-article', 'acme/post', ['category' => ProjectCategory::Article]);

    $this->artisan('projects:prune-missing-repos --delete --no-interaction')->assertSuccessful();

    expect(Project::query()->where('slug', 'an-article')->exists())->toBeTrue();
});

it('never deletes a repo it could not verify (5xx = unknown)', function () {
    Http::swap($factory = new Factory);
    $factory->preventStrayRequests();
    $factory->fake(['api.github.com/repos/acme/flaky' => Http::response('', 503)]);

    repoProject('flaky-pkg', 'acme/flaky');

    $this->artisan('projects:prune-missing-repos --delete --no-interaction')->assertSuccessful();

    expect(Project::query()->where('slug', 'flaky-pkg')->exists())->toBeTrue();
});

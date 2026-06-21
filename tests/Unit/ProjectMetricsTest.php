<?php

declare(strict_types=1);

use App\Enums\PackageType;
use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Support\ProjectMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use JeffersonGoncalves\GitHubClient\Exceptions\GitHubRateLimitException;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The snapshot cache persists across tests on the array store — flush it so
    // one test's cached repo can't suppress another's HTTP-call assertions.
    Cache::flush();
    // The post-save observer dispatches another sync — fake the queue so it
    // doesn't recurse on the sync driver and double the call counts.
    Queue::fake();
    // GraphQL always requires auth; without a token fetchRepoGraphql bails early.
    config(['services.github.token' => 'test-token']);
});

function metricsProject(array $attributes = []): Project
{
    return Project::query()->create(array_merge([
        'name' => 'Repo',
        'slug' => 'repo',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/owner/repo',
        'stars' => 0,
        'downloads' => 0,
    ], $attributes));
}

/**
 * @param  list<string>  $branches
 */
function graphqlRepo(int $stars = 1, ?string $language = null, array $topics = [], string $defaultBranch = 'main', array $branches = ['main']): array
{
    return ['data' => ['repository' => [
        'stargazerCount' => $stars,
        'primaryLanguage' => $language !== null ? ['name' => $language] : null,
        'repositoryTopics' => ['nodes' => array_map(fn ($t) => ['topic' => ['name' => $t]], $topics)],
        'defaultBranchRef' => ['name' => $defaultBranch],
        'refs' => ['nodes' => array_map(fn ($b) => ['name' => $b], $branches)],
    ]]];
}

it('applies stars, language and topics from one GraphQL call (no REST snapshot/branches)', function () {
    Http::fake([
        'api.github.com/repos/*/contributors*' => Http::response([]),
        'api.github.com/graphql' => Http::response(graphqlRepo(stars: 5, language: 'PHP', topics: ['laravel'])),
    ]);

    $project = metricsProject(['versions' => ['v3'], 'stars' => 0]);
    ProjectMetrics::sync($project);

    expect($project->fresh()->stars)->toBe(5);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/graphql');
    // No REST snapshot or branches endpoints are hit anymore.
    Http::assertNotSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/repos/owner/repo/branches'));
});

it('repairs branch overrides from the branches in the GraphQL snapshot', function () {
    Http::fake([
        'api.github.com/repos/*/contributors*' => Http::response([]),
        'api.github.com/graphql' => Http::response(graphqlRepo(branches: ['3.x', 'main'])),
    ]);

    // v3 resolves to its literal 3.x branch; v4 falls back to the default main.
    $project = metricsProject(['versions' => ['v3', 'v4']]);
    ProjectMetrics::sync($project);

    expect($project->fresh()->branch_overrides)->toBe(['1.x' => '3.x', '2.x' => 'main']);
});

it('skips branch repair when the snapshot has no branches', function () {
    Http::fake([
        'api.github.com/repos/*/contributors*' => Http::response([]),
        'api.github.com/graphql' => Http::response(graphqlRepo(branches: [])),
    ]);

    $project = metricsProject(['versions' => ['v3'], 'branch_overrides' => ['1.x' => 'custom']]);
    ProjectMetrics::sync($project);

    // Empty branch list = no data this run; existing overrides are left intact.
    expect($project->fresh()->branch_overrides)->toBe(['1.x' => 'custom']);
});

it('still persists stars when the contributors endpoint times out', function () {
    // Giant repos (NixOS/nixpkgs) make the contributors call exhaust the retry
    // budget and throw ConnectionException. That must not discard the stars the
    // GraphQL call already produced — the contributors timeout is swallowed.
    config(['services.github.username' => 'owner']);
    Http::fake([
        'api.github.com/repos/*/contributors*' => fn () => throw new ConnectionException('cURL error 28: Operation timed out'),
        'api.github.com/graphql' => Http::response(graphqlRepo(stars: 25015)),
    ]);

    $project = metricsProject(['stars' => 0]);
    $changed = ProjectMetrics::sync($project);

    expect($changed)->toBeTrue()
        ->and($project->fresh()->stars)->toBe(25015)
        ->and($project->fresh()->user_contributions)->toBe(0);
});

it('reads user contributions from a single contributors page', function () {
    config(['services.github.username' => 'owner']);
    Http::fake([
        'api.github.com/repos/*/contributors*' => Http::response([['login' => 'owner', 'contributions' => 42]]),
        'api.github.com/graphql' => Http::response(graphqlRepo()),
    ]);

    $project = metricsProject();
    ProjectMetrics::sync($project);

    expect($project->fresh()->user_contributions)->toBe(42);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'page=2'));
});

it('throws GitHubRateLimitException on a GraphQL 403 rate limit', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response('', 403, [
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Reset' => (string) (time() + 120),
        ]),
    ]);

    expect(fn () => ProjectMetrics::sync(metricsProject()))
        ->toThrow(GitHubRateLimitException::class);
});

it('throws when GraphQL returns a RATE_LIMITED error on a 200', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(
            ['errors' => [['type' => 'RATE_LIMITED', 'message' => 'rate limited']]],
            200,
            ['X-RateLimit-Reset' => (string) (time() + 90)],
        ),
    ]);

    expect(fn () => ProjectMetrics::sync(metricsProject()))
        ->toThrow(GitHubRateLimitException::class);
});

it('completes without throwing on a non-rate-limit GraphQL failure', function () {
    Http::fake([
        'api.github.com/repos/*/contributors*' => Http::response([]),
        'api.github.com/graphql' => Http::response('', 500),
    ]);

    expect(ProjectMetrics::sync(metricsProject()))->toBeBool();
});

it('does not attach a packagist_url for a borrowed composer name the repo does not own', function () {
    // A tutorial / app-skeleton repo ships `composer.json` `name: laravel/laravel`
    // without owning the package. The metrics sync must not re-derive a
    // packagist_url from that borrowed name — Packagist's repository field for
    // laravel/laravel points at laravel/laravel, a foreign owner.
    Http::fake([
        'api.github.com/repos/*/contributors*' => Http::response([]),
        'api.github.com/graphql' => Http::response(graphqlRepo()),
        'raw.githubusercontent.com/owner/repo/main/composer.json' => Http::response(['name' => 'laravel/laravel']),
        'packagist.org/packages/laravel/laravel.json' => Http::response([
            'package' => ['repository' => 'https://github.com/laravel/laravel'],
        ]),
    ]);

    $project = metricsProject(['category' => ProjectCategory::LaravelPackage, 'package_type' => PackageType::Composer]);
    ProjectMetrics::sync($project);

    expect($project->fresh()->packagist_url)->toBeNull();
});

it('attaches a packagist_url when Packagist points back at the repo', function () {
    // A genuinely-published package: Packagist's repository field resolves to the
    // same GitHub owner, so the derived URL is adopted.
    Http::fake([
        'api.github.com/repos/*/contributors*' => Http::response([]),
        'api.github.com/graphql' => Http::response(graphqlRepo()),
        'raw.githubusercontent.com/owner/repo/main/composer.json' => Http::response(['name' => 'owner/repo']),
        'packagist.org/packages/owner/repo.json' => Http::response([
            'package' => [
                'repository' => 'https://github.com/owner/repo',
                'downloads' => ['total' => 123],
                'versions' => [],
            ],
        ]),
    ]);

    $project = metricsProject(['category' => ProjectCategory::LaravelPackage, 'package_type' => PackageType::Composer]);
    ProjectMetrics::sync($project);

    expect($project->fresh()->packagist_url)->toBe('https://packagist.org/packages/owner/repo');
});

it('serves the cached snapshot without hitting GraphQL while fresh', function () {
    Cache::put('github:repo-snapshot:owner/repo', [
        'payload' => ['stars' => 9, 'language' => null, 'topics' => [], 'default_branch' => 'main', 'branches' => []],
        'fetched_at' => time(),
    ], now()->addDays(7));

    Http::fake([
        'api.github.com/repos/*/contributors*' => Http::response([]),
        // No graphql fake registered — the fresh cache entry must short-circuit it.
    ]);

    ProjectMetrics::sync(metricsProject());

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'graphql'));
});

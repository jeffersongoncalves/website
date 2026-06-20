<?php

declare(strict_types=1);

use App\Enums\PackageType;
use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Support\ProjectMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Cold cache so a cached repo snapshot from one test can't suppress the
    // HTTP-call assertions of another.
    Cache::flush();
    // The post-save observer dispatches another sync — fake the queue so it
    // doesn't recurse on the sync driver and double the call counts.
    Queue::fake();
    // GraphQL always requires auth; without a token fetchRepoGraphql bails early.
    config(['services.github.token' => 'test-token']);
    // Empty username short-circuits fetchUserContributions (no contributors call)
    // unless a test explicitly opts in.
    config(['services.github.username' => '']);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function projectMetrics_project(array $attributes = []): Project
{
    return Project::query()->create(array_merge([
        'name' => 'Coverage Repo',
        'slug' => 'coverage-repo',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/owner/repo',
        'stars' => 0,
        'downloads' => 0,
    ], $attributes));
}

/**
 * @param  list<string>  $topics
 * @param  list<string>  $branches
 */
function projectMetrics_graphql(int $stars = 1, ?string $language = null, array $topics = [], string $defaultBranch = 'main', array $branches = ['main']): array
{
    return ['data' => ['repository' => [
        'stargazerCount' => $stars,
        'primaryLanguage' => $language !== null ? ['name' => $language] : null,
        'repositoryTopics' => ['nodes' => array_map(fn ($t) => ['topic' => ['name' => $t]], $topics)],
        'defaultBranchRef' => ['name' => $defaultBranch],
        'refs' => ['nodes' => array_map(fn ($b) => ['name' => $b], $branches)],
    ]]];
}

// ---------------------------------------------------------------------------
// Docker Hub pulls
// ---------------------------------------------------------------------------

it('reads pull_count for a namespaced Docker Hub repo', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql()),
        'hub.docker.com/v2/repositories/plausible/analytics/' => Http::response(['pull_count' => 12345678]),
    ]);

    $project = projectMetrics_project(['docker_url' => 'https://hub.docker.com/r/plausible/analytics']);
    ProjectMetrics::sync($project);

    expect($project->fresh()->downloads)->toBe(12345678)
        ->and($project->fresh()->downloads_label)->toBe('12.3M');
});

it('resolves an official Docker Hub image through the library namespace', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql()),
        'hub.docker.com/v2/repositories/library/nginx/' => Http::response(['pull_count' => 5000]),
    ]);

    $project = projectMetrics_project(['docker_url' => 'https://hub.docker.com/_/nginx']);
    ProjectMetrics::sync($project);

    expect($project->fresh()->downloads)->toBe(5000)
        ->and($project->fresh()->downloads_label)->toBe('5k');
});

it('leaves downloads at zero for a ghcr.io docker url with no public counter', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql()),
    ]);

    $project = projectMetrics_project(['docker_url' => 'https://ghcr.io/owner/repo']);
    ProjectMetrics::sync($project);

    expect($project->fresh()->downloads)->toBe(0);
    // ghcr.io is not hub.docker.com → no Docker Hub API call is attempted.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'hub.docker.com'));
});

it('leaves downloads unchanged when the Docker Hub API errors', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql()),
        'hub.docker.com/v2/repositories/owner/image/' => Http::response('', 500),
    ]);

    $project = projectMetrics_project(['docker_url' => 'https://hub.docker.com/r/owner/image', 'downloads' => 7]);
    ProjectMetrics::sync($project);

    expect($project->fresh()->downloads)->toBe(7);
});

it('ignores a non-numeric Docker Hub pull_count', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql()),
        'hub.docker.com/v2/repositories/owner/image/' => Http::response(['pull_count' => null]),
    ]);

    $project = projectMetrics_project(['docker_url' => 'https://hub.docker.com/r/owner/image', 'downloads' => 3]);
    ProjectMetrics::sync($project);

    expect($project->fresh()->downloads)->toBe(3);
});

// ---------------------------------------------------------------------------
// npm downloads
// ---------------------------------------------------------------------------

it('reads npm last-month downloads for an npm_url', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql()),
        'api.npmjs.org/downloads/point/last-month/leftpad' => Http::response(['downloads' => 54321]),
    ]);

    $project = projectMetrics_project(['npm_url' => 'https://www.npmjs.com/package/leftpad']);
    ProjectMetrics::sync($project);

    expect($project->fresh()->downloads)->toBe(54321)
        ->and($project->fresh()->downloads_label)->toBe('54.3k');
});

it('leaves downloads unchanged when the npm downloads endpoint errors', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql()),
        'api.npmjs.org/downloads/point/last-month/*' => Http::response('', 404),
    ]);

    $project = projectMetrics_project(['npm_url' => 'https://www.npmjs.com/package/leftpad', 'downloads' => 9]);
    ProjectMetrics::sync($project);

    expect($project->fresh()->downloads)->toBe(9);
});

// ---------------------------------------------------------------------------
// Packagist downloads + keywords
// ---------------------------------------------------------------------------

it('reads packagist total downloads and merges keywords into topics', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql()),
        'packagist.org/packages/owner/repo.json' => Http::response(['package' => [
            'downloads' => ['total' => 4567],
            'versions' => [
                'dev-main' => ['keywords' => ['Laravel', 'Filament']],
                '1.0.0' => ['keywords' => ['filament']],
            ],
        ]]),
    ]);

    $project = projectMetrics_project(['packagist_url' => 'https://packagist.org/packages/owner/repo']);
    ProjectMetrics::sync($project);

    expect($project->fresh()->downloads)->toBe(4567)
        ->and($project->fresh()->downloads_label)->toBe('4.6k')
        ->and($project->fresh()->topics)->toContain('laravel')
        ->and($project->fresh()->topics)->toContain('filament');
});

it('returns empty keywords and null downloads on a packagist error', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql()),
        'packagist.org/packages/owner/repo.json' => Http::response('', 500),
    ]);

    $project = projectMetrics_project([
        'packagist_url' => 'https://packagist.org/packages/owner/repo',
        'downloads' => 11,
        'topics' => ['seeded'],
    ]);
    ProjectMetrics::sync($project);

    expect($project->fresh()->downloads)->toBe(11)
        ->and($project->fresh()->topics)->toBe(['seeded']);
});

it('ignores packagist versions that are not an array', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql()),
        'packagist.org/packages/owner/repo.json' => Http::response(['package' => [
            'downloads' => ['total' => 200],
            'versions' => 'not-an-array',
        ]]),
    ]);

    $project = projectMetrics_project(['packagist_url' => 'https://packagist.org/packages/owner/repo']);
    ProjectMetrics::sync($project);

    expect($project->fresh()->downloads)->toBe(200);
});

// ---------------------------------------------------------------------------
// JetBrains downloads (takes priority via docs_url)
// ---------------------------------------------------------------------------

it('reads JetBrains plugin downloads from a docs_url', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql()),
        'plugins.jetbrains.com/api/plugins/31190' => Http::response(['downloads' => 8800]),
    ]);

    $project = projectMetrics_project([
        'docs_url' => 'https://plugins.jetbrains.com/plugin/31190-worktree-env-configurator',
    ]);
    ProjectMetrics::sync($project);

    expect($project->fresh()->downloads)->toBe(8800)
        ->and($project->fresh()->downloads_label)->toBe('8.8k');
});

it('leaves downloads unchanged when the JetBrains API errors', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql()),
        'plugins.jetbrains.com/api/plugins/*' => Http::response('', 500),
    ]);

    $project = projectMetrics_project([
        'docs_url' => 'https://plugins.jetbrains.com/plugin/31190-worktree',
        'downloads' => 4,
    ]);
    ProjectMetrics::sync($project);

    expect($project->fresh()->downloads)->toBe(4);
});

// ---------------------------------------------------------------------------
// resolveNpmUrl
// ---------------------------------------------------------------------------

it('adopts an npm_url when the registry repository points back at the repo', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql()),
        'raw.githubusercontent.com/owner/repo/main/package.json' => Http::response(['name' => '@owner/widget']),
        'registry.npmjs.org/@owner/widget' => Http::response([
            'repository' => ['url' => 'git+https://github.com/owner/repo.git'],
        ]),
        'api.npmjs.org/downloads/point/last-month/@owner/widget' => Http::response(['downloads' => 100]),
    ]);

    $project = projectMetrics_project(['package_type' => PackageType::Npm]);
    ProjectMetrics::sync($project);

    expect($project->fresh()->npm_url)->toBe('https://www.npmjs.com/package/@owner/widget')
        ->and($project->fresh()->downloads)->toBe(100);
});

it('skips npm_url for a private monorepo root manifest', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql()),
        'raw.githubusercontent.com/owner/repo/main/package.json' => Http::response(['name' => '@owner/root', 'private' => true]),
    ]);

    $project = projectMetrics_project(['package_type' => PackageType::Npm]);
    ProjectMetrics::sync($project);

    expect($project->fresh()->npm_url)->toBeNull();
    // private:true short-circuits before the ownership registry lookup.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'registry.npmjs.org'));
});

it('skips npm_url for a borrowed package name the repo does not own', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql()),
        'raw.githubusercontent.com/owner/repo/main/package.json' => Http::response(['name' => 'vue']),
        'registry.npmjs.org/vue' => Http::response([
            'repository' => ['url' => 'git+https://github.com/vuejs/core.git'],
        ]),
    ]);

    $project = projectMetrics_project(['package_type' => PackageType::Npm]);
    ProjectMetrics::sync($project);

    expect($project->fresh()->npm_url)->toBeNull();
});

it('skips npm_url when the package.json fetch fails', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql()),
        'raw.githubusercontent.com/owner/repo/main/package.json' => Http::response('', 404),
    ]);

    $project = projectMetrics_project(['package_type' => PackageType::Npm]);
    ProjectMetrics::sync($project);

    expect($project->fresh()->npm_url)->toBeNull();
});

it('skips npm_url for a malformed package.json name', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql()),
        'raw.githubusercontent.com/owner/repo/main/package.json' => Http::response(['name' => 'Has Spaces!']),
    ]);

    $project = projectMetrics_project(['package_type' => PackageType::Npm]);
    ProjectMetrics::sync($project);

    expect($project->fresh()->npm_url)->toBeNull();
});

// ---------------------------------------------------------------------------
// repairBranchOverrides — manual keep + no-versions early return
// ---------------------------------------------------------------------------

it('keeps a manual branch override that still points at a real branch', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql(branches: ['develop', 'main'])),
    ]);

    // v3 keeps its manual `develop` (still a real branch); v4 falls back to main.
    $project = projectMetrics_project([
        'versions' => ['v3', 'v4'],
        'branch_overrides' => ['1.x' => 'develop'],
    ]);
    ProjectMetrics::sync($project);

    expect($project->fresh()->branch_overrides)->toBe(['1.x' => 'develop', '2.x' => 'main']);
});

it('skips branch repair for a project with no versions', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql(branches: ['1.x', 'main'])),
    ]);

    $project = projectMetrics_project(['branch_overrides' => ['1.x' => 'whatever']]);
    ProjectMetrics::sync($project);

    expect($project->fresh()->branch_overrides)->toBe(['1.x' => 'whatever']);
});

it('falls back to the auto-branch when no candidate branch matches', function () {
    // versions=[generic] → autoBranch 1.x; no v-prefix, 1.x absent from branches,
    // and no default-branch match → resolved stays null → uses 1.x fallback.
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql(defaultBranch: 'trunk', branches: ['feature'])),
    ]);

    $project = projectMetrics_project(['versions' => ['legacy']]);
    ProjectMetrics::sync($project);

    expect($project->fresh()->branch_overrides)->toBe(['1.x' => '1.x']);
});

// ---------------------------------------------------------------------------
// fetchUserContributions branches
// ---------------------------------------------------------------------------

it('records zero contributions when the configured user is absent from contributors', function () {
    config(['services.github.username' => 'owner']);
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql()),
        'api.github.com/repos/*/contributors*' => Http::response([
            ['login' => 'someoneelse', 'contributions' => 99],
            'not-an-array-entry',
        ]),
    ]);

    $project = projectMetrics_project(['user_contributions' => 5]);
    ProjectMetrics::sync($project);

    expect($project->fresh()->user_contributions)->toBe(0);
});

it('returns null contributions on a contributors API error', function () {
    config(['services.github.username' => 'owner']);
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql()),
        'api.github.com/repos/*/contributors*' => Http::response('', 500),
    ]);

    $project = projectMetrics_project(['user_contributions' => 7]);
    ProjectMetrics::sync($project);

    // A failed contributors lookup must not overwrite the existing value.
    expect($project->fresh()->user_contributions)->toBe(7);
});

it('demotes a maintainer flag to daily-driver on zero verified contributions', function () {
    config(['services.github.username' => 'owner']);
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql()),
        'api.github.com/repos/*/contributors*' => Http::response([
            ['login' => 'stranger', 'contributions' => 3],
        ]),
    ]);

    $project = projectMetrics_project([
        'is_maintainer' => true,
        'is_daily_driver' => false,
        'user_contributions' => 0,
    ]);
    ProjectMetrics::sync($project);

    expect($project->fresh()->is_maintainer)->toBeFalse()
        ->and($project->fresh()->is_daily_driver)->toBeTrue();
});

it('skips contribution lookup entirely when no username is configured', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(projectMetrics_graphql()),
    ]);

    ProjectMetrics::sync(projectMetrics_project());

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/contributors'));
});

// ---------------------------------------------------------------------------
// formatDownloads (pure unit)
// ---------------------------------------------------------------------------

it('formats download counts with k/M abbreviations', function (int $n, string $expected) {
    expect(ProjectMetrics::formatDownloads($n))->toBe($expected);
})->with([
    [0, '0'],
    [999, '999'],
    [1000, '1k'],
    [1500, '1.5k'],
    [12000, '12k'],
    [1000000, '1M'],
    [1500000, '1.5M'],
    [12345678, '12.3M'],
]);

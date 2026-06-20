<?php

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Jobs\ImportGithubRepoJob;
use App\Jobs\ImportNpmPackageJob;
use App\Jobs\ImportWebsiteJob;
use App\Jobs\ImportYoutubeChannelJob;
use App\Jobs\PersistSiteStatsJob;
use App\Models\Project;
use App\Models\SiteStat;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush(); // ProjectImporter / SiteStats memoise.
});

// A clean HTTP factory with only the given stubs — the global beforeEach
// registers permissive github stubs and first-registered wins, so a later
// override would never be reached. Re-arm stray-request protection after.
function importJobs_stub(array $stubs): void
{
    Http::swap(new Factory);
    Http::fake($stubs);
    Http::preventStrayRequests();
}

// ----------------------------------------------------------------------------
// ImportNpmPackageJob
// ----------------------------------------------------------------------------

it('imports an npm package into a published javascript_package project', function () {
    importJobs_stub([
        'registry.npmjs.org/acme-pkg' => Http::response([
            'name' => 'acme-pkg',
            'description' => 'An acme package',
            'license' => 'MIT',
            'repository' => 'https://github.com/acme/pkg',
            'keywords' => ['acme'],
        ]),
    ]);

    (new ImportNpmPackageJob('acme-pkg'))->handle();

    $row = Project::query()->where('npm_url', 'https://www.npmjs.com/package/acme-pkg')->first();
    expect($row)->not->toBeNull()
        ->and($row->status)->toBe(ProjectStatus::Published)
        ->and($row->category)->toBe(ProjectCategory::JavascriptPackage)
        ->and($row->github_url)->toBe('https://github.com/acme/pkg');

    // Idempotent — the npm_url pre-check short-circuits the second run.
    (new ImportNpmPackageJob('acme-pkg'))->handle();
    expect(Project::query()->where('npm_url', 'https://www.npmjs.com/package/acme-pkg')->count())->toBe(1);
});

it('falls back to a synthesised row when the npm registry has no manifest', function () {
    importJobs_stub([
        // 404 → fetchNpmRegistry returns null → fromNpm yields an error result
        // (no 'fields'), so the job uses its own fallback attributes.
        'registry.npmjs.org/*' => Http::response('', 404),
    ]);

    (new ImportNpmPackageJob('ghost-pkg', 'tool'))->handle();

    $row = Project::query()->where('npm_url', 'https://www.npmjs.com/package/ghost-pkg')->first();
    expect($row)->not->toBeNull()
        ->and($row->category)->toBe(ProjectCategory::Tool)
        ->and($row->status)->toBe(ProjectStatus::Published)
        ->and($row->name)->toBe('Ghost Pkg');
});

it('fills the npm_url onto a repo already cadastrado via github (cross-source dedup)', function () {
    Project::query()->create([
        'slug' => 'acme-pkg-existing',
        'name' => 'Pkg',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/acme/pkg',
        'published_at' => now(),
    ]);

    importJobs_stub([
        'registry.npmjs.org/acme-pkg' => Http::response([
            'name' => 'acme-pkg',
            'description' => 'An acme package',
            'license' => 'MIT',
            'repository' => 'https://github.com/acme/pkg',
        ]),
    ]);

    (new ImportNpmPackageJob('acme-pkg'))->handle();

    // No new row — the existing github row absorbed the npm link.
    expect(Project::query()->count())->toBe(1);
    expect(Project::query()->where('github_url', 'https://github.com/acme/pkg')->first()->npm_url)
        ->toBe('https://www.npmjs.com/package/acme-pkg');
});

it('releases the npm import job when GitHub rate-limits the repo recovery, creating nothing', function () {
    importJobs_stub([
        // A monorepo `directory` forces a GitHub API call to resolve the branch;
        // that call hits the rate limit and fromNpm rethrows.
        'registry.npmjs.org/*' => Http::response([
            'name' => 'mono-pkg',
            'description' => 'd',
            'license' => 'MIT',
            'repository' => ['type' => 'git', 'url' => 'https://github.com/acme/mono.git', 'directory' => 'packages/foo'],
        ]),
        'api.github.com/*' => Http::response('', 403, [
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Reset' => (string) (time() + 120),
        ]),
    ]);

    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('release')->once();

    (new ImportNpmPackageJob('mono-pkg'))->setJob($queueJob)->handle();

    expect(Project::query()->count())->toBe(0);
});

it('logs context when ImportNpmPackageJob fails', function () {
    Log::shouldReceive('error')
        ->once()
        ->with('ImportNpmPackageJob failed', Mockery::on(fn ($ctx) => $ctx['package'] === 'broken-pkg' && $ctx['error'] === 'boom'));

    (new ImportNpmPackageJob('broken-pkg'))->failed(new RuntimeException('boom'));
});

// ----------------------------------------------------------------------------
// ImportGithubRepoJob
// ----------------------------------------------------------------------------

it('falls back to a synthesised row when the github repo cannot be fetched', function () {
    importJobs_stub([
        // 404 on the repo endpoint → fetchRepo null → fromGithub error result,
        // so the job builds its own fallback attributes from the URL slug.
        'api.github.com/repos/*' => Http::response('', 404),
    ]);

    (new ImportGithubRepoJob('https://github.com/acme/missing', 'awesome_list'))->handle();

    $row = Project::query()->where('github_url', 'https://github.com/acme/missing')->first();
    expect($row)->not->toBeNull()
        ->and($row->category)->toBe(ProjectCategory::AwesomeList)
        ->and($row->status)->toBe(ProjectStatus::Published)
        ->and($row->slug)->toBe('acme-missing');
});

it('logs context when ImportGithubRepoJob fails', function () {
    Log::shouldReceive('error')
        ->once()
        ->with('ImportGithubRepoJob failed', Mockery::on(fn ($ctx) => $ctx['github_url'] === 'https://github.com/acme/widget' && $ctx['error'] === 'boom'));

    (new ImportGithubRepoJob('https://github.com/acme/widget'))->failed(new RuntimeException('boom'));
});

// ----------------------------------------------------------------------------
// ImportWebsiteJob / ImportYoutubeChannelJob
// ----------------------------------------------------------------------------

it('logs context when ImportWebsiteJob fails', function () {
    Log::shouldReceive('error')
        ->once()
        ->with('ImportWebsiteJob failed', Mockery::on(fn ($ctx) => $ctx['url'] === 'https://example.com' && $ctx['name'] === 'Example' && $ctx['error'] === 'boom'));

    (new ImportWebsiteJob('https://example.com', 'Example'))->failed(new RuntimeException('boom'));
});

it('logs context when ImportYoutubeChannelJob fails', function () {
    Log::shouldReceive('error')
        ->once()
        ->with('ImportYoutubeChannelJob failed', Mockery::on(fn ($ctx) => $ctx['handle'] === 'someone' && $ctx['name'] === 'Someone' && $ctx['error'] === 'boom'));

    (new ImportYoutubeChannelJob('someone', 'Someone'))->failed(new RuntimeException('boom'));
});

// ----------------------------------------------------------------------------
// PersistSiteStatsJob
// ----------------------------------------------------------------------------

it('persists the site stats singleton row from local + github data', function () {
    // No token → sponsors/contributions short-circuit to zero, followers come
    // from the global users stub (200, followers 0). The job must still write
    // the locally-derived category counts.
    config(['services.github.token' => null]);

    createProject([
        'slug' => 'a-plugin',
        'name' => 'A Plugin',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
    ]);

    (new PersistSiteStatsJob)->handle();

    $stat = SiteStat::query()->first();
    expect($stat)->not->toBeNull()
        ->and($stat->filament)->toBeGreaterThanOrEqual(1)
        ->and($stat->synced_at)->not->toBeNull();
});

it('releases PersistSiteStatsJob without writing zeros when GitHub is rate-limiting', function () {
    // With a token configured, an empty user payload means the call failed —
    // compute() throws GithubRateLimitException so persist() never overwrites
    // the cached followers/sponsors with zeros.
    config(['services.github.token' => 'test-token']);

    importJobs_stub([
        'api.github.com/users/*' => Http::response('', 403),
    ]);

    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('release')->once();

    (new PersistSiteStatsJob)->setJob($queueJob)->handle();

    // No row written — the rate-limit release happened before persist().
    expect(SiteStat::query()->count())->toBe(0);
});

it('logs context when PersistSiteStatsJob fails', function () {
    Log::shouldReceive('error')
        ->once()
        ->with('PersistSiteStatsJob failed', Mockery::on(fn ($ctx) => $ctx['error'] === 'boom'));

    (new PersistSiteStatsJob)->failed(new RuntimeException('boom'));
});

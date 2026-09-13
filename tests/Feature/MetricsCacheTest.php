<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\SiteStat;
use App\Support\GithubReadme;
use App\Support\SiteStats;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use JeffersonGoncalves\GitHubReadme\Models\ReadmeCache;

function makeProject(int $i, ProjectCategory $category, ?string $githubUrl = null): Project
{
    return Project::query()->create([
        'name' => "Project {$i}",
        'slug' => "project-{$i}",
        'category' => $category,
        'status' => ProjectStatus::Published,
        'github_url' => $githubUrl,
        'stars' => 10,
        'downloads' => 100,
    ]);
}

it('persists site stats to the database and reads them back without recomputing', function () {
    // Two repos owned by the site login + one third-party repo. `repos` counts
    // only the owned ones; `catalogue` is the full published total.
    makeProject(1, ProjectCategory::FilamentPlugin, 'https://github.com/jeffersongoncalves/project-1');
    makeProject(2, ProjectCategory::FilamentPlugin, 'https://github.com/jeffersongoncalves/project-2');
    makeProject(3, ProjectCategory::LaravelPackage, 'https://github.com/someoneelse/project-3');

    SiteStats::persist();

    expect(SiteStat::query()->count())->toBe(1);

    $row = SiteStat::query()->first();
    expect($row->repos)->toBe(2);
    expect($row->catalogue)->toBe(3);
    expect($row->filament)->toBe(2);
    expect($row->laravel)->toBe(1);
    expect($row->stars)->toBe(30);
    expect($row->synced_at)->not->toBeNull();

    Http::fake(fn () => throw new RuntimeException('site stats read should not hit the network'));
    expect(SiteStats::all()['repos'])->toBe(2);
    expect(SiteStats::all()['catalogue'])->toBe(3);
});

it('caches the assembled stats so repeat reads do not re-query', function () {
    makeProject(1, ProjectCategory::FilamentPlugin);
    SiteStats::persist(); // writes the row and (via the saved hook) clears the cache

    DB::enableQueryLog();
    $first = SiteStats::all();
    $second = SiteStats::all();
    $hits = collect(DB::getQueryLog())
        ->filter(fn ($q) => str_contains($q['query'], 'site_stats'))
        ->count();
    DB::disableQueryLog();

    expect($first)->toBe($second)
        ->and($hits)->toBe(1); // first read warmed the cache, second served from it
});

it('invalidates the cached stats when the singleton is written', function () {
    SiteStats::persist();
    expect(SiteStats::all()['filament'])->toBe(0); // warms the cache at 0

    makeProject(1, ProjectCategory::FilamentPlugin);
    SiteStats::persist(); // saved hook forgets the cache

    expect(SiteStats::all()['filament'])->toBe(1);
});

it('computes the per-language breakdown busiest first', function () {
    makeProject(1, ProjectCategory::PhpPackage)->update(['language' => 'PHP']);
    makeProject(2, ProjectCategory::PhpPackage)->update(['language' => 'PHP']);
    makeProject(3, ProjectCategory::JavascriptPackage)->update(['language' => 'JavaScript']);
    makeProject(4, ProjectCategory::Application); // no language

    SiteStats::persist();

    expect(SiteStats::all()['languages'])->toBe([
        ['language' => 'PHP', 'total' => 2],
        ['language' => 'JavaScript', 'total' => 1],
    ]);
});

it('computes the per-topic breakdown busiest first', function () {
    makeProject(1, ProjectCategory::PhpPackage)->update(['topics' => ['laravel', 'filament']]);
    makeProject(2, ProjectCategory::PhpPackage)->update(['topics' => ['laravel']]);
    makeProject(3, ProjectCategory::Application)->update(['topics' => ['cli']]);

    SiteStats::persist();

    expect(SiteStats::all()['topics'])->toBe([
        ['topic' => 'laravel', 'total' => 2],
        ['topic' => 'filament', 'total' => 1],
        ['topic' => 'cli', 'total' => 1],
    ]);
});

it('fetches the real sponsor count over GraphQL when a github token is configured', function () {
    // The global beforeEach fake already stubs these same URLs (with a zero
    // sponsor count) and Http::fake() resolves first-registered-stub-wins —
    // swap in a fresh factory so only this test's fakes are in play (same
    // trick PluginsSyncTest/SyncStarredReposTest use for the same reason).
    Http::swap(new HttpFactory);
    config(['services.github.token' => 'test-token']);
    Http::fake([
        'api.github.com/users/*' => Http::response(['followers' => 10, 'public_repos' => 5]),
        'api.github.com/graphql' => Http::response(['data' => ['user' => [
            'sponsorshipsAsMaintainer' => ['totalCount' => 7],
            'contributionsCollection' => ['contributionCalendar' => ['totalContributions' => 0, 'weeks' => []]],
        ]]]),
    ]);

    SiteStats::persist();

    expect(SiteStat::query()->first()->public_sponsors)->toBe(7);
});

it('returns zero sponsors without a github token', function () {
    config(['services.github.token' => null]);

    SiteStats::persist();

    expect(SiteStat::query()->first()->public_sponsors)->toBe(0);
});

it('skips a project row whose topics column holds malformed json instead of an array', function () {
    $project = makeProject(1, ProjectCategory::PhpPackage);
    $project->update(['topics' => ['laravel']]);
    DB::table('projects')->where('id', $project->id)->update(['topics' => '"not-an-array"']);

    makeProject(2, ProjectCategory::PhpPackage)->update(['topics' => ['filament']]);

    SiteStats::persist();

    // The malformed row is silently skipped, not fatal — only the healthy
    // second row's topic is counted.
    expect(SiteStats::all()['topics'])->toBe([
        ['topic' => 'filament', 'total' => 1],
    ]);
});

it('formats homeCards and osCards with k/M abbreviation thresholds', function () {
    SiteStat::query()->create([
        'repos' => 3,
        'catalogue' => 5,
        'filament' => 2,
        'laravel' => 1,
        'starter' => 1,
        'followers' => 500, // below 1k — no suffix
        'stars' => 1500, // above 1k — k suffix
        'downloads' => 2_500_000, // above 1M — M suffix
        'downloads_packagist' => 2_500, // above 1k, below 1M — k suffix via scaleM/suffixM
        'downloads_npm' => 500, // below 1k — no suffix via scaleM/suffixM
        'downloads_jetbrains' => 0,
        'public_sponsors' => 4,
        'synced_at' => now(),
    ]);

    $home = SiteStats::homeCards();
    expect($home[1])->toBe(['label_key' => 'os.followers', 'target' => 500]); // abbrevK, no suffix
    expect($home[2])->toBe(['label_key' => 'os.downloads', 'target' => 2.5, 'suffix' => 'M', 'decimals' => 1]);

    $os = SiteStats::osCards();
    $byLabel = collect($os)->keyBy('label_key');
    expect($byLabel['os.downloads_packagist'])->toBe(['label_key' => 'os.downloads_packagist', 'target' => 2.5, 'suffix' => 'k', 'decimals' => 1]);
    expect($byLabel['os.downloads_npm'])->toBe(['label_key' => 'os.downloads_npm', 'target' => 500]);
    expect($byLabel['os.stars'])->toBe(['label_key' => 'os.stars', 'target' => 1.5, 'suffix' => 'k', 'decimals' => 1]);
    expect($byLabel['os.public_sponsors'])->toBe(['label_key' => 'os.public_sponsors', 'target' => 4]);
});

it('renders the README and writes it to the github disk', function () {
    Storage::fake('github');

    $html = GithubReadme::fetchHtml('https://github.com/jeffersongoncalves/example');

    expect($html)->toContain('README');

    $cache = ReadmeCache::query()->where('repo', 'jeffersongoncalves/example')->first();
    expect($cache)->not->toBeNull();
    expect($cache->html_path)->not->toBeNull();
    expect($cache->checked_at)->not->toBeNull();
    expect(Storage::disk('github')->exists($cache->html_path))->toBeTrue();
});

it('serves the README from disk within the check window without calling GitHub', function () {
    Storage::fake('github');
    Storage::disk('github')->put('readme/jeffersongoncalves__example/default.html', '<h1>Cached</h1>');

    ReadmeCache::query()->create([
        'repo' => 'jeffersongoncalves/example',
        'ref' => 'default',
        'etag' => 'W/"v1"',
        'html_path' => 'readme/jeffersongoncalves__example/default.html',
        'fetched_at' => now()->subMinutes(2),
        'checked_at' => now()->subMinutes(2),
    ]);

    $html = GithubReadme::fetchHtml('https://github.com/jeffersongoncalves/example');

    expect($html)->toBe('<h1>Cached</h1>');
    Http::assertNothingSent();
});

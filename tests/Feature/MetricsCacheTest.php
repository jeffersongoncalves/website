<?php

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ReadmeCache;
use App\Models\SiteStat;
use App\Support\GithubReadme;
use App\Support\SiteStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeProject(int $i, ProjectCategory $category): Project
{
    return Project::query()->create([
        'name' => "Project {$i}",
        'category' => $category,
        'description' => 'A test project.',
        'status' => ProjectStatus::Published,
        'stars' => 10,
        'downloads' => 100,
    ]);
}

it('persists site stats to the database and reads them back without recomputing', function () {
    Http::fake(['api.github.com/*' => Http::response([], 200)]);

    makeProject(1, ProjectCategory::FilamentPlugin);
    makeProject(2, ProjectCategory::FilamentPlugin);
    makeProject(3, ProjectCategory::LaravelPackage);

    SiteStats::persist();

    expect(SiteStat::query()->count())->toBe(1);

    $row = SiteStat::query()->first();
    expect($row->repos)->toBe(3);
    expect($row->filament)->toBe(2);
    expect($row->laravel)->toBe(1);
    expect($row->stars)->toBe(30);
    expect($row->synced_at)->not->toBeNull();

    // Reading goes straight to the row — no HTTP, no recompute.
    Http::fake(fn () => throw new RuntimeException('site stats read should not hit the network'));
    expect(SiteStats::all()['repos'])->toBe(3);
});

it('renders the README and writes it to the github disk', function () {
    Storage::fake('github');

    $html = GithubReadme::fetchHtml('https://github.com/jeffersongoncalves/example');

    expect($html)->toContain('README'); // body from the global HTTP fake in tests/Pest.php

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
    Http::assertNothingSent(); // inside the check window — no GitHub request at all
});

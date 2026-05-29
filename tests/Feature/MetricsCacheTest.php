<?php

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ReadmeCache;
use App\Models\SiteStat;
use App\Support\GithubReadme;
use App\Support\SiteStats;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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

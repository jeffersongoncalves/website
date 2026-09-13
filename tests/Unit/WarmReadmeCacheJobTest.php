<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Jobs\WarmReadmeCacheJob;
use App\Models\Project;
use App\Support\ReadmeImageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use JeffersonGoncalves\GitHubReadme\Models\ReadmeCache;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('github'));

it('guards GitHub calls with overlap and rate-limit middleware', function () {
    $project = Project::query()->create([
        'name' => 'Repo',
        'slug' => 'repo',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'stars' => 0,
        'downloads' => 0,
    ]);

    $middleware = (new WarmReadmeCacheJob($project))->middleware();

    expect($middleware)->toHaveCount(2)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[1])->toBeInstanceOf(RateLimited::class);
});

it('warms every tracked version\'s branch, deduped, for a Filament plugin', function () {
    Http::fake(['api.github.com/repos/*/readme*' => Http::response('# README', 200, ['Content-Type' => 'text/plain'])]);

    $project = Project::query()->create([
        'name' => 'Repo',
        'slug' => 'repo',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/owner/repo',
        'versions' => ['v4', 'v5'],
        // v4 and v5 both resolve to `main` via this override — should warm once, not twice.
        'branch_overrides' => ['1.x' => 'main', '2.x' => 'main'],
        'stars' => 0,
        'downloads' => 0,
    ]);

    (new WarmReadmeCacheJob($project))->handle();

    expect(ReadmeCache::query()->where('repo', 'owner/repo')->count())->toBe(1)
        ->and(ReadmeCache::query()->where('repo', 'owner/repo')->value('ref'))->toBe('main');
});

it('warms the plain readme_branch for a project with no tracked versions', function () {
    Http::fake(['api.github.com/repos/*/readme*' => Http::response('# README', 200, ['Content-Type' => 'text/plain'])]);

    $project = Project::query()->create([
        'name' => 'Repo',
        'slug' => 'repo',
        'category' => ProjectCategory::LaravelPackage,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/owner/repo',
        'readme_branch' => 'develop',
        'stars' => 0,
        'downloads' => 0,
    ]);

    (new WarmReadmeCacheJob($project))->handle();

    expect(ReadmeCache::query()->where(['repo' => 'owner/repo', 'ref' => 'develop'])->exists())->toBeTrue();
});

it('warms every GitHub-hosted image found in the fetched README', function () {
    Http::fake([
        'api.github.com/repos/*/readme*' => Http::response(
            "# README\n\n<img src=\"https://raw.githubusercontent.com/owner/repo/main/banner.png\">",
            200,
            ['Content-Type' => 'text/plain']
        ),
        'raw.githubusercontent.com/owner/repo/main/banner.png' => Http::response('PNGBYTES', 200, ['Content-Type' => 'image/png']),
    ]);

    $project = Project::query()->create([
        'name' => 'Repo',
        'slug' => 'repo',
        'category' => ProjectCategory::LaravelPackage,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/owner/repo',
        'readme_branch' => 'main',
        'stars' => 0,
        'downloads' => 0,
    ]);

    (new WarmReadmeCacheJob($project))->handle();

    Storage::disk('github')->assertExists(
        ReadmeImageCache::path('https://raw.githubusercontent.com/owner/repo/main/banner.png')
    );
});

it('warms the npm README for a package with no github_url', function () {
    Http::fake(['registry.npmjs.org/*' => Http::response(['readme' => '# Pkg'])]);

    $project = Project::query()->create([
        'name' => 'Pkg',
        'slug' => 'pkg',
        'category' => ProjectCategory::JavascriptPackage,
        'status' => ProjectStatus::Published,
        'npm_url' => 'https://www.npmjs.com/package/pkg',
        'stars' => 0,
        'downloads' => 0,
    ]);

    (new WarmReadmeCacheJob($project))->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'registry.npmjs.org/pkg'));
});

it('does nothing when the npm package has no readme', function () {
    Http::fake(['registry.npmjs.org/*' => Http::response(['name' => 'pkg'])]);

    $project = Project::query()->create([
        'name' => 'Pkg',
        'slug' => 'pkg',
        'category' => ProjectCategory::JavascriptPackage,
        'status' => ProjectStatus::Published,
        'npm_url' => 'https://www.npmjs.com/package/pkg',
        'stars' => 0,
        'downloads' => 0,
    ]);

    (new WarmReadmeCacheJob($project))->handle();

    expect(ReadmeCache::query()->count())->toBe(0);
});

it('bounds WarmReadmeCacheJob retries by time', function () {
    $project = Project::query()->create([
        'name' => 'Repo',
        'slug' => 'repo',
        'category' => ProjectCategory::LaravelPackage,
        'status' => ProjectStatus::Published,
        'stars' => 0,
        'downloads' => 0,
    ]);

    expect((new WarmReadmeCacheJob($project, staggerSeconds: 30))->retryUntil())
        ->toBeGreaterThan(now()->addMinutes(59));
});

it('logs a warning instead of throwing when fetching the readme fails', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $project = Project::query()->create([
        'name' => 'Repo',
        'slug' => 'repo',
        'category' => ProjectCategory::LaravelPackage,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/owner/repo',
        'readme_branch' => 'main',
        'stars' => 0,
        'downloads' => 0,
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->with('WarmReadmeCacheJob failed', Mockery::on(fn ($ctx) => $ctx['project'] === 'repo'));

    (new WarmReadmeCacheJob($project))->handle();
});

it('logs context when WarmReadmeCacheJob permanently fails', function () {
    $project = Project::query()->create([
        'name' => 'Repo',
        'slug' => 'repo',
        'category' => ProjectCategory::LaravelPackage,
        'status' => ProjectStatus::Published,
        'stars' => 0,
        'downloads' => 0,
    ]);

    Log::shouldReceive('error')
        ->once()
        ->with('WarmReadmeCacheJob permanently failed', Mockery::on(fn ($ctx) => $ctx['project'] === 'repo' && $ctx['error'] === 'boom'));

    (new WarmReadmeCacheJob($project))->failed(new RuntimeException('boom'));
});

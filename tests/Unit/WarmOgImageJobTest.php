<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Jobs\WarmOgImageJob;
use App\Models\Project;
use App\Support\OgImageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('github'));

it('resolves the disk path for a slug\'s cached card', function () {
    expect(OgImageCache::path('repo'))->toBe('og-images/repo');
});

it('guards GitHub calls with overlap and rate-limit middleware', function () {
    $project = Project::query()->create([
        'name' => 'Repo',
        'slug' => 'repo',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'stars' => 0,
        'downloads' => 0,
    ]);

    $middleware = (new WarmOgImageJob($project))->middleware();

    expect($middleware)->toHaveCount(2)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[1])->toBeInstanceOf(RateLimited::class);
});

it('fetches and persists a github social card to disk', function () {
    Http::fake(['opengraph.githubassets.com/*' => Http::response('PNGBYTES', 200, ['Content-Type' => 'image/png'])]);

    $project = Project::query()->create([
        'name' => 'Repo',
        'slug' => 'repo',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/owner/repo',
        'stars' => 0,
        'downloads' => 0,
    ]);

    (new WarmOgImageJob($project))->handle();

    Storage::disk('github')->assertExists('og-images/repo');
    expect(Storage::disk('github')->get('og-images/repo.type'))->toBe('image/png');
});

it('does nothing for a project with no image source', function () {
    $project = Project::query()->create([
        'name' => 'Repo',
        'slug' => 'repo',
        'category' => ProjectCategory::Saas,
        'status' => ProjectStatus::Published,
        'stars' => 0,
        'downloads' => 0,
    ]);

    (new WarmOgImageJob($project))->handle();

    Storage::disk('github')->assertMissing('og-images/repo');
});

it('bounds WarmOgImageJob retries by time', function () {
    $project = Project::query()->create([
        'name' => 'Repo',
        'slug' => 'repo',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'stars' => 0,
        'downloads' => 0,
    ]);

    expect((new WarmOgImageJob($project, staggerSeconds: 30))->retryUntil())
        ->toBeGreaterThan(now()->addMinutes(59));
});

it('logs a warning instead of throwing when warming the social card fails', function () {
    Http::fake(['opengraph.githubassets.com/*' => Http::response('', 500)]);

    $project = Project::query()->create([
        'name' => 'Repo',
        'slug' => 'repo',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/owner/repo',
        'stars' => 0,
        'downloads' => 0,
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->with('WarmOgImageJob failed', Mockery::on(fn ($ctx) => $ctx['project'] === 'repo'));

    (new WarmOgImageJob($project))->handle();

    Storage::disk('github')->assertMissing('og-images/repo');
});

it('logs context when WarmOgImageJob permanently fails', function () {
    $project = Project::query()->create([
        'name' => 'Repo',
        'slug' => 'repo',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'stars' => 0,
        'downloads' => 0,
    ]);

    Log::shouldReceive('error')
        ->once()
        ->with('WarmOgImageJob permanently failed', Mockery::on(fn ($ctx) => $ctx['project'] === 'repo' && $ctx['error'] === 'boom'));

    (new WarmOgImageJob($project))->failed(new RuntimeException('boom'));
});

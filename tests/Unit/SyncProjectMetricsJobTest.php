<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Jobs\SyncProjectMetricsJob;
use App\Jobs\WarmReadmeCacheJob;
use App\Models\Project;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

it('guards GitHub calls with overlap and rate-limit middleware', function () {
    $project = Project::query()->create([
        'name' => 'Repo',
        'slug' => 'repo',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'stars' => 0,
        'downloads' => 0,
    ]);

    $middleware = (new SyncProjectMetricsJob($project))->middleware();

    expect($middleware)->toHaveCount(2)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[1])->toBeInstanceOf(RateLimited::class);
});

it('bounds SyncProjectMetricsJob retries by time', function () {
    $project = Project::query()->create([
        'name' => 'Repo',
        'slug' => 'repo',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'stars' => 0,
        'downloads' => 0,
    ]);

    expect((new SyncProjectMetricsJob($project, staggerSeconds: 30))->retryUntil())
        ->toBeGreaterThan(now()->addMinutes(59));
});

it('syncs metrics without error when github has nothing new to report', function () {
    Http::swap(new Factory);
    Http::preventStrayRequests();
    config(['services.github.token' => 'fake-token']);

    Http::fake([
        'api.github.com/graphql' => Http::response(['data' => ['repository' => null]], 200),
    ]);

    $project = Project::query()->create([
        'name' => 'Repo',
        'slug' => 'repo',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/acme/repo',
        'is_maintainer' => false,
        'stars' => 0,
        'downloads' => 0,
    ]);

    (new SyncProjectMetricsJob($project))->handle();
})->throwsNoExceptions();

it('releases SyncProjectMetricsJob when GitHub rate-limits the graphql call', function () {
    Http::swap(new Factory);
    Http::preventStrayRequests();
    config(['services.github.token' => 'fake-token']);

    Http::fake([
        'api.github.com/graphql' => Http::response('', 403, [
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Reset' => (string) (time() + 90),
        ]),
    ]);
    Bus::fake();

    $project = Project::query()->create([
        'name' => 'Repo',
        'slug' => 'repo',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/acme/repo',
        'is_maintainer' => false,
        'stars' => 0,
        'downloads' => 0,
    ]);

    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('release')->once();

    (new SyncProjectMetricsJob($project))->setJob($queueJob)->handle();

    Bus::assertNotDispatched(WarmReadmeCacheJob::class);
});

it('logs a warning and does not release on a non-rate-limit sync failure', function () {
    Http::swap(new Factory);
    Http::preventStrayRequests();
    config(['services.github.token' => 'fake-token']);

    Http::fake([
        'api.github.com/graphql' => Http::response('not json', 200),
    ]);
    Bus::fake();

    $project = Project::query()->create([
        'name' => 'Repo',
        'slug' => 'repo',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/acme/repo',
        'is_maintainer' => false,
        'stars' => 0,
        'downloads' => 0,
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->with('SyncProjectMetricsJob failed', Mockery::on(fn ($ctx) => $ctx['project'] === 'repo'));

    (new SyncProjectMetricsJob($project))->handle();

    Bus::assertNotDispatched(WarmReadmeCacheJob::class);
});

it('logs context when SyncProjectMetricsJob permanently fails', function () {
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
        ->with('SyncProjectMetricsJob permanently failed', Mockery::on(fn ($ctx) => $ctx['project'] === 'repo' && $ctx['error'] === 'boom'));

    (new SyncProjectMetricsJob($project))->failed(new RuntimeException('boom'));
});

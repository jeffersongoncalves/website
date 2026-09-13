<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Jobs\ImportStarredRepoJob;
use App\Jobs\SyncStarredReposJob;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Read starred_at as the TRUE raw DB scalar (via the query builder, bypassing
 * the model's datetime cast) and interpret it as UTC. The job stores the column
 * in UTC; the Eloquent cast would re-read it in app.timezone and report an
 * instant shifted by the offset, so we read it raw to assert what was persisted.
 */
function storedStarredAtUtc(string $githubUrl): ?string
{
    $raw = DB::table('projects')->where('github_url', $githubUrl)->value('starred_at');

    return $raw === null ? null : CarbonImmutable::parse($raw, 'UTC')->toIso8601ZuluString();
}

/**
 * @param  list<array{0: string, 1: string}>  $repos  list of [ownerRepo, starredAt]
 * @return list<array<string, mixed>>
 */
function starItems(array $repos): array
{
    return array_map(fn (array $r): array => [
        'starred_at' => $r[1],
        'repo' => ['html_url' => 'https://github.com/'.$r[0]],
    ], $repos);
}

beforeEach(function (): void {
    config(['services.github.username' => 'tester', 'services.github.token' => 'fake-token']);

    // The global Pest fake (tests/Pest.php) stubs `api.github.com/users/*` with
    // a single user object, which shadows the starred-list endpoint (fakes are
    // matched in registration order, first-match wins). Drop the resolved HTTP
    // factory so the stubs each test registers below take precedence.
    app()->forgetInstance(HttpFactory::class);
    Http::clearResolvedInstances();
    Http::preventStrayRequests();

    // Swallow the jobs ProjectObserver dispatches on create so they don't run
    // (Prism/network) or pollute the dispatch assertions.
    Bus::fake();
});

it('dispatches an import only for stars newer than the cursor and stops at the cursor', function (): void {
    // Cursor = newest existing starred_at.
    createProject([
        'slug' => 'old-repo',
        'name' => 'Old Repo',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Draft,
        'github_url' => 'https://github.com/acme/old-repo',
        'starred_at' => '2026-05-20T10:00:00Z',
    ]);

    Http::fake([
        '*api.github.com/users/*/starred*' => Http::response(starItems([
            ['acme/new-two', '2026-05-25T10:00:00Z'],   // newer → dispatch
            ['acme/new-one', '2026-05-22T10:00:00Z'],   // newer → dispatch
            ['acme/old-repo', '2026-05-20T10:00:00Z'],  // == cursor → stop
            ['acme/ancient', '2026-05-01T10:00:00Z'],   // never reached
        ])),
    ]);

    (new SyncStarredReposJob)->handle();

    Bus::assertDispatchedTimes(ImportStarredRepoJob::class, 2);
    Bus::assertDispatched(ImportStarredRepoJob::class, fn (ImportStarredRepoJob $j) => $j->htmlUrl === 'https://github.com/acme/new-two');
    Bus::assertNotDispatched(ImportStarredRepoJob::class, fn (ImportStarredRepoJob $j) => str_contains($j->htmlUrl, 'ancient'));
});

it('sends the star+json Accept header so timestamps come back', function (): void {
    Http::fake(['*api.github.com/*' => Http::response([])]);

    (new SyncStarredReposJob)->handle();

    Http::assertSent(fn ($request) => $request->hasHeader('Accept', 'application/vnd.github.star+json')
        && $request->hasHeader('Authorization', 'Bearer fake-token'));
});

it('is idempotent: a second run with the cursor advanced dispatches nothing', function (): void {
    createProject([
        'slug' => 'newest',
        'name' => 'Newest',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Draft,
        'github_url' => 'https://github.com/acme/newest',
        'starred_at' => '2026-05-25T10:00:00Z',
    ]);

    Http::fake([
        '*api.github.com/users/*/starred*' => Http::response(starItems([
            ['acme/newest', '2026-05-25T10:00:00Z'],
            ['acme/older', '2026-05-10T10:00:00Z'],
        ])),
    ]);

    (new SyncStarredReposJob)->handle();

    Bus::assertNotDispatched(ImportStarredRepoJob::class);
});

it('--full ignores the cursor and re-scans everything', function (): void {
    createProject([
        'slug' => 'newest',
        'name' => 'Newest',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Draft,
        'github_url' => 'https://github.com/acme/newest',
        'starred_at' => '2026-05-25T10:00:00Z',
    ]);

    Http::fake([
        '*api.github.com/users/*/starred*' => Http::response(starItems([
            ['acme/newest', '2026-05-25T10:00:00Z'],
            ['acme/older', '2026-05-10T10:00:00Z'],
        ])),
    ]);

    (new SyncStarredReposJob(full: true))->handle();

    Bus::assertDispatchedTimes(ImportStarredRepoJob::class, 2);
});

it('staggers each dispatched import by 1s so a bulk sync does not flood the queue at once', function (): void {
    Http::fake([
        '*api.github.com/users/*/starred*' => Http::response(starItems([
            ['acme/third', '2026-05-27T10:00:00Z'],
            ['acme/second', '2026-05-26T10:00:00Z'],
            ['acme/first', '2026-05-25T10:00:00Z'],
        ])),
    ]);

    (new SyncStarredReposJob)->handle();

    $delays = Bus::dispatched(ImportStarredRepoJob::class)
        ->map(fn (ImportStarredRepoJob $job) => $job->delay)
        ->all();

    // Dispatch order follows the API's desc order (third, second, first) —
    // each gets a strictly later delay than the one before it.
    expect($delays)->toHaveCount(3);
    expect($delays[0])->toBeLessThan($delays[1])->and($delays[1])->toBeLessThan($delays[2]);
});

it('follows pagination past a full page', function (): void {
    $page1 = array_map(
        fn (int $i): array => [
            'starred_at' => sprintf('2026-06-%02dT10:00:00Z', ($i % 28) + 1),
            'repo' => ['html_url' => "https://github.com/acme/repo-{$i}"],
        ],
        range(1, 100),
    );

    $page2 = starItems([['acme/last', '2026-04-01T10:00:00Z']]);

    // Anchor on the trailing &page=N — `*page=1*` would also match the
    // `per_page=100` substring present in every URL.
    Http::fake([
        '*&page=1' => Http::response($page1),
        '*&page=2' => Http::response($page2),
    ]);

    (new SyncStarredReposJob(full: true))->handle();

    // 100 from page 1 + 1 from page 2 (short page stops the loop after it).
    Bus::assertDispatchedTimes(ImportStarredRepoJob::class, 101);
});

it('creates a published project for a new starred repo and never duplicates it', function (): void {
    Http::fake([
        '*api.github.com/repos/acme/widget' => Http::response([
            'default_branch' => 'main',
            'license' => ['spdx_id' => 'MIT'],
            'language' => 'PHP',
            'topics' => ['laravel'],
            'description' => 'A widget',
            'homepage' => null,
        ]),
        // composer.json / package.json / branches / docker probes — 404 is fine.
        '*' => Http::response('', 404),
    ]);

    $job = new ImportStarredRepoJob('https://github.com/acme/widget', '2026-05-25T10:00:00Z');
    $job->handle();
    $job->handle(); // second run must not create a duplicate

    $rows = Project::query()->where('github_url', 'https://github.com/acme/widget')->get();
    expect($rows)->toHaveCount(1);
    expect($rows->first()->status)->toBe(ProjectStatus::Published);
    expect($rows->first()->is_maintainer)->toBeFalse();
    expect(storedStarredAtUtc('https://github.com/acme/widget'))->toBe('2026-05-25T10:00:00Z');
});

it('never downgrades or overwrites an already-published repo, only back-fills starred_at', function (): void {
    $existing = Project::query()->create([
        'slug' => 'curated',
        'name' => 'Curated Name',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/acme/curated',
    ]);

    Http::fake(fn () => throw new RuntimeException('existing repo must not hit the importer'));

    (new ImportStarredRepoJob('https://github.com/acme/curated', '2026-05-25T10:00:00Z'))->handle();

    $existing->refresh();
    expect($existing->status)->toBe(ProjectStatus::Published);
    expect($existing->name)->toBe('Curated Name');
    expect(storedStarredAtUtc('https://github.com/acme/curated'))->toBe('2026-05-25T10:00:00Z');
});

it('does not skip a new star inside the app timezone offset window', function (): void {
    // Regression: storing starred_at in local time would shift the derived
    // cursor by the app offset (e.g. +3h for America/Sao_Paulo), so a star a
    // couple of hours after the last-synced one would compare as already-known
    // and be dropped. UTC end-to-end keeps the 1h gap visible.
    createProject([
        'slug' => 'last-synced',
        'name' => 'Last Synced',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Draft,
        'github_url' => 'https://github.com/acme/last-synced',
        'starred_at' => '2026-05-25T10:00:00Z',
    ]);

    Http::fake([
        '*api.github.com/users/*/starred*' => Http::response(starItems([
            ['acme/fresh', '2026-05-25T11:00:00Z'],       // 1h after cursor → must dispatch
            ['acme/last-synced', '2026-05-25T10:00:00Z'], // == cursor → stop
        ])),
    ]);

    (new SyncStarredReposJob)->handle();

    Bus::assertDispatchedTimes(ImportStarredRepoJob::class, 1);
    Bus::assertDispatched(ImportStarredRepoJob::class, fn (ImportStarredRepoJob $j) => str_contains($j->htmlUrl, 'fresh'));
});

// ----------------------------------------------------------------------------
// SyncStarredReposJob — remaining branches
// ----------------------------------------------------------------------------

it('skips the sync and logs a warning when no github username is configured', function (): void {
    config(['services.github.username' => null]);

    Log::shouldReceive('warning')
        ->once()
        ->with('SyncStarredReposJob skipped: services.github.username not set');

    (new SyncStarredReposJob)->handle();

    Bus::assertNotDispatched(ImportStarredRepoJob::class);
});

it('bounds SyncStarredReposJob retries by time and guards with overlap/rate-limit middleware', function (): void {
    $job = new SyncStarredReposJob;

    expect($job->retryUntil())->toBeGreaterThan(now()->addMinutes(90));

    $middleware = $job->middleware();
    expect($middleware)->toHaveCount(2)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[1])->toBeInstanceOf(RateLimited::class);
});

it('releases SyncStarredReposJob when the starred-list page is rate-limited', function (): void {
    Http::fake([
        '*api.github.com/users/*/starred*' => Http::response('', 403, [
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Reset' => (string) (time() + 90),
        ]),
    ]);

    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('release')->once();

    (new SyncStarredReposJob)->setJob($queueJob)->handle();

    Bus::assertNotDispatched(ImportStarredRepoJob::class);
});

it('stops the page and logs a warning when GitHub returns a non-rate-limit error', function (): void {
    Http::fake([
        '*api.github.com/users/*/starred*' => Http::response('', 500),
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->with('SyncStarredReposJob: GitHub returned an error', Mockery::on(fn ($ctx) => $ctx['status'] === 500 && $ctx['page'] === 1));
    Log::shouldReceive('info')->once();

    (new SyncStarredReposJob)->handle();

    Bus::assertNotDispatched(ImportStarredRepoJob::class);
});

it('stops when the starred list page decodes to an empty array', function (): void {
    Http::fake([
        '*api.github.com/users/*/starred*' => Http::response([]),
    ]);

    (new SyncStarredReposJob)->handle();

    Bus::assertNotDispatched(ImportStarredRepoJob::class);
});

it('logs context when SyncStarredReposJob fails', function (): void {
    Log::shouldReceive('error')
        ->once()
        ->with('SyncStarredReposJob failed', Mockery::on(fn ($ctx) => $ctx['full'] === false && $ctx['error'] === 'boom'));

    (new SyncStarredReposJob)->failed(new RuntimeException('boom'));
});

it('does not treat an ordinary 403 with quota remaining and no Retry-After as a rate limit', function (): void {
    // throwIfRateLimited's early-return branch: 403 but neither signal present.
    Http::fake([
        '*api.github.com/users/*/starred*' => Http::response('', 403, ['X-RateLimit-Remaining' => '10']),
    ]);

    Log::shouldReceive('warning')->once();
    Log::shouldReceive('info')->once();

    (new SyncStarredReposJob)->handle();

    Bus::assertNotDispatched(ImportStarredRepoJob::class);
});

it('honours a Retry-After header over X-RateLimit-Reset on a secondary-limit 429', function (): void {
    Http::fake([
        '*api.github.com/users/*/starred*' => Http::response('', 429, ['Retry-After' => '45']),
    ]);

    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('release')->once();

    (new SyncStarredReposJob)->setJob($queueJob)->handle();

    Bus::assertNotDispatched(ImportStarredRepoJob::class);
});

// ----------------------------------------------------------------------------
// ImportStarredRepoJob — remaining branches
// ----------------------------------------------------------------------------

it('bounds ImportStarredRepoJob retries by time and guards with overlap/rate-limit middleware', function (): void {
    $job = new ImportStarredRepoJob('https://github.com/acme/widget', '2026-05-25T10:00:00Z', staggerSeconds: 30);

    expect($job->retryUntil())->toBeGreaterThan(now()->addMinutes(59));

    $middleware = $job->middleware();
    expect($middleware)->toHaveCount(2)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[1])->toBeInstanceOf(RateLimited::class);
});

it('releases ImportStarredRepoJob when the importer hits a github rate limit', function (): void {
    Http::fake([
        'api.github.com/repos/*' => Http::response('', 403, [
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Reset' => (string) (time() + 60),
        ]),
    ]);

    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('release')->once();

    (new ImportStarredRepoJob('https://github.com/acme/widget', '2026-05-25T10:00:00Z'))
        ->setJob($queueJob)->handle();

    expect(Project::query()->count())->toBe(0);
});

it('drops a star permanently when the importer reports the repo does not exist', function (): void {
    Http::fake(['*api.github.com/repos/*' => Http::response('', 404)]);

    Log::shouldReceive('warning')
        ->once()
        ->with('ImportStarredRepoJob: importer failed permanently', Mockery::on(fn ($ctx) => $ctx['error'] === 'repo_not_found'));

    (new ImportStarredRepoJob('https://github.com/acme/gone', '2026-05-25T10:00:00Z'))->handle();

    expect(Project::query()->count())->toBe(0);
});

it('fills a docs_url-matched website seed instead of duplicating a starred repo', function (): void {
    $seed = Project::query()->create([
        'slug' => 'site-acme',
        'name' => 'Acme Site',
        'category' => ProjectCategory::Website,
        'status' => ProjectStatus::Published,
        'docs_url' => 'https://acme.example.com',
        'published_at' => now(),
    ]);

    Http::fake([
        '*api.github.com/repos/acme/widget' => Http::response([
            'full_name' => 'acme/widget',
            'html_url' => 'https://github.com/acme/widget',
            'default_branch' => 'main',
            'license' => ['spdx_id' => 'MIT'],
            'homepage' => 'https://acme.example.com',
        ]),
        '*' => Http::response('', 404),
    ]);

    (new ImportStarredRepoJob('https://github.com/acme/widget', '2026-05-25T10:00:00Z'))->handle();

    // Unlike ImportGithubRepoJob's second pass, this branch only back-fills
    // starred_at — it does not fillMissing() the rest onto the matched row.
    expect(Project::query()->count())->toBe(1);
    expect($seed->refresh())
        ->github_url->toBeNull()
        ->starred_at->not->toBeNull();
});

it('logs context when ImportStarredRepoJob fails', function (): void {
    Log::shouldReceive('error')
        ->once()
        ->with('ImportStarredRepoJob failed', Mockery::on(fn ($ctx) => $ctx['html_url'] === 'https://github.com/acme/widget' && $ctx['error'] === 'boom'));

    (new ImportStarredRepoJob('https://github.com/acme/widget', '2026-05-25T10:00:00Z'))->failed(new RuntimeException('boom'));
});

// ---------------------------------------------------------------------------
// projects:sync-stars (SyncStarredRepos console command) — thin dispatcher
// ---------------------------------------------------------------------------

it('dispatches a non-full star sync job by default', function (): void {
    $this->artisan('projects:sync-stars')
        ->expectsOutputToContain('Dispatched star sync on the `github` queue.')
        ->assertSuccessful();

    // This file's beforeEach calls Bus::fake() (not Queue::fake()) — dispatch()
    // for a ShouldQueue job routes through the Bus contract either way, so the
    // assertion belongs on Bus here, not Queue.
    Bus::assertDispatched(SyncStarredReposJob::class, fn (SyncStarredReposJob $job) => $job->full === false);
});

it('dispatches a full star sync job with --full', function (): void {
    $this->artisan('projects:sync-stars --full')->assertSuccessful();

    Bus::assertDispatched(SyncStarredReposJob::class, fn (SyncStarredReposJob $job) => $job->full === true);
});

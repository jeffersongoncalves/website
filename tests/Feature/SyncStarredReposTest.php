<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Jobs\ImportStarredRepoJob;
use App\Jobs\SyncStarredReposJob;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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

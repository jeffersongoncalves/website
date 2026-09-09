<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Jobs\PersistSiteStatsJob;
use App\Jobs\PurgeMisattributedPackageLinksJob;
use App\Jobs\SyncProjectMetricsJob;
use App\Jobs\WarmReadmeCacheJob;
use App\Models\Admin;
use App\Models\Project;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

/**
 * Build a published Project quickly. Prefixed to avoid clashing with the global
 * createProject() helper (which doesn't default the columns these commands read).
 *
 * @param  array<string, mixed>  $overrides
 */
function consoleCmd_publishedProject(array $overrides = []): Project
{
    return createProject(array_merge([
        'slug' => 'a-project',
        'name' => 'A Project',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'published_at' => now()->subDay(),
    ], $overrides));
}

// ---------------------------------------------------------------------------
// admin:create (CreateAdmin)
// ---------------------------------------------------------------------------

it('creates a verified, active admin from --name/--email options', function () {
    $this->artisan('admin:create', [
        '--name' => 'Jane Doe',
        '--email' => 'jane@example.com',
    ])->assertSuccessful();

    $admin = Admin::query()->where('email', 'jane@example.com')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->name)->toBe('Jane Doe')
        ->and($admin->status)->toBeTrue()
        ->and($admin->hasVerifiedEmail())->toBeTrue()
        // Str::password(16) generated a real (hashed) password.
        ->and($admin->password)->not->toBeEmpty();
});

it('prompts for name and email when options are omitted', function () {
    $this->artisan('admin:create')
        ->expectsQuestion('What is the admin name?', 'Prompted Admin')
        ->expectsQuestion('What is the admin email?', 'prompted@example.com')
        ->assertSuccessful();

    expect(Admin::query()->where('email', 'prompted@example.com')->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// projects:check-repo-slugs (CheckProjectRepoSlugs) — read-only audit, no HTTP
// ---------------------------------------------------------------------------

it('passes when every github project has a canonical owner-prefixed slug', function () {
    consoleCmd_publishedProject([
        'slug' => 'mbostock-d3',
        'repo' => 'd3',
        'github_url' => 'https://github.com/mbostock/d3',
    ]);

    $this->artisan('projects:check-repo-slugs')
        ->expectsOutputToContain('none')
        ->assertExitCode(0);
});

it('fails when a github project slug dropped its owner prefix (bare-name slug)', function () {
    // Importer would derive slug "mbostock-d3"; this row only has "d3".
    consoleCmd_publishedProject([
        'slug' => 'd3',
        'repo' => 'd3',
        'github_url' => 'https://github.com/mbostock/d3',
    ]);

    $this->artisan('projects:check-repo-slugs')
        ->expectsOutputToContain('Missing-owner slugs')
        ->expectsOutputToContain('1') // summary count
        ->assertExitCode(1); // self::FAILURE — non-empty bareSlug
});

it('reports lower-confidence mismatches only with --all', function () {
    // Slug differs but is NOT the bare repo name -> "other" bucket (not a failure).
    // Repo column also mismatches.
    consoleCmd_publishedProject([
        'slug' => 'totally-custom-slug',
        'repo' => 'wrong-repo',
        'github_url' => 'https://github.com/mbostock/d3',
    ]);

    // Without --all: succeeds (no missing-owner case) and nudges to re-run.
    $this->artisan('projects:check-repo-slugs')
        ->expectsOutputToContain('Re-run with --all')
        ->assertExitCode(0);

    // With --all: the extra sections are rendered.
    $this->artisan('projects:check-repo-slugs', ['--all' => true])
        ->expectsOutputToContain('Other slug mismatches')
        ->expectsOutputToContain('Repo column !=')
        ->assertExitCode(0);
});

it('classifies an unparseable github_url with --all', function () {
    consoleCmd_publishedProject([
        'slug' => 'weird',
        'repo' => 'weird',
        'github_url' => 'https://example.com/not-a-github-repo',
    ]);

    $this->artisan('projects:check-repo-slugs', ['--all' => true])
        ->expectsOutputToContain('Unparseable github_url')
        ->assertExitCode(0);
});

it('ignores projects without a github_url', function () {
    consoleCmd_publishedProject([
        'slug' => 'no-github',
        'github_url' => null,
    ]);

    $this->artisan('projects:check-repo-slugs')->assertExitCode(0);
});

// ---------------------------------------------------------------------------
// projects:sync-metrics (SyncProjectMetrics) — Bus batch + trailing stats job
// ---------------------------------------------------------------------------

it('dispatches a batch of per-project sync jobs', function () {
    Bus::fake();

    consoleCmd_publishedProject(['slug' => 'p1', 'name' => 'P1']);
    consoleCmd_publishedProject(['slug' => 'p2', 'name' => 'P2']);

    $this->artisan('projects:sync-metrics')
        ->expectsOutputToContain('Dispatched 2 project sync jobs')
        ->assertSuccessful();

    Bus::assertBatched(fn ($batch) => $batch->name === 'sync-project-metrics'
        && $batch->jobs->count() === 2
        && $batch->jobs->every(fn ($job) => $job instanceof SyncProjectMetricsJob));
});

it('skips projects synced within the last 20h on a full-catalogue run, but not via --slug', function () {
    Bus::fake();

    $fresh = consoleCmd_publishedProject(['slug' => 'fresh', 'name' => 'Fresh']);
    $fresh->forceFill(['last_synced_at' => now()->subHours(2)])->save();
    $stale = consoleCmd_publishedProject(['slug' => 'stale', 'name' => 'Stale']);
    $stale->forceFill(['last_synced_at' => now()->subHours(25)])->save();
    consoleCmd_publishedProject(['slug' => 'never', 'name' => 'Never']);

    $this->artisan('projects:sync-metrics')
        ->expectsOutputToContain('Dispatched 2 project sync jobs')
        ->assertSuccessful();

    Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 2
        && $batch->jobs->pluck('project.slug')->all() === ['stale', 'never']);

    // --slug is an explicit manual resync — always runs, freshness aside.
    $this->artisan('projects:sync-metrics', ['--slug' => 'fresh'])
        ->expectsOutputToContain('Dispatched 1 project sync jobs')
        ->assertSuccessful();
});

it('limits the sync batch to a single slug via --slug', function () {
    Bus::fake();

    consoleCmd_publishedProject(['slug' => 'keep', 'name' => 'Keep']);
    consoleCmd_publishedProject(['slug' => 'skip', 'name' => 'Skip']);

    $this->artisan('projects:sync-metrics', ['--slug' => 'keep'])
        ->expectsOutputToContain('Dispatched 1 project sync jobs')
        ->assertSuccessful();

    Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 1);
});

it('skips drafts and dispatches the stats job directly when nothing is published', function () {
    // A draft project is excluded by the published() scope.
    consoleCmd_publishedProject(['slug' => 'draft', 'status' => ProjectStatus::Draft]);

    $this->artisan('projects:sync-metrics')
        ->expectsOutputToContain('No projects to sync')
        ->assertSuccessful();

    // Queue is faked globally (beforeEach) — the stats job lands on the queue.
    Queue::assertPushed(PersistSiteStatsJob::class);
});

// ---------------------------------------------------------------------------
// projects:purge-package-links (PurgePackageLinks)
// ---------------------------------------------------------------------------

it('dispatches a purge job only for github projects that carry a package link', function () {
    // Matches: github + packagist.
    consoleCmd_publishedProject([
        'slug' => 'gh-packagist',
        'github_url' => 'https://github.com/owner/a',
        'packagist_url' => 'https://packagist.org/packages/owner/a',
    ]);
    // Matches: github + npm.
    consoleCmd_publishedProject([
        'slug' => 'gh-npm',
        'github_url' => 'https://github.com/owner/b',
        'npm_url' => 'https://www.npmjs.com/package/b',
    ]);
    // Excluded: github but no package link.
    consoleCmd_publishedProject([
        'slug' => 'gh-only',
        'github_url' => 'https://github.com/owner/c',
    ]);
    // Excluded: package link but no github_url.
    consoleCmd_publishedProject([
        'slug' => 'no-github',
        'github_url' => null,
        'packagist_url' => 'https://packagist.org/packages/owner/d',
    ]);

    $this->artisan('projects:purge-package-links')
        ->expectsOutputToContain('Dispatched 2 package-link verification job(s).')
        ->assertSuccessful();

    Queue::assertPushed(PurgeMisattributedPackageLinksJob::class, 2);
});

it('dispatches no purge jobs when no project carries package links', function () {
    consoleCmd_publishedProject([
        'slug' => 'gh-only',
        'github_url' => 'https://github.com/owner/c',
    ]);

    $this->artisan('projects:purge-package-links')
        ->expectsOutputToContain('Dispatched 0 package-link verification job(s).')
        ->assertSuccessful();

    Queue::assertNotPushed(PurgeMisattributedPackageLinksJob::class);
});

// ---------------------------------------------------------------------------
// projects:warm-readme-cache (WarmReadmeCache)
// ---------------------------------------------------------------------------

it('dispatches a warm job only for projects with a github_url or npm_url', function () {
    consoleCmd_publishedProject(['slug' => 'gh', 'github_url' => 'https://github.com/owner/a']);
    consoleCmd_publishedProject(['slug' => 'npm', 'github_url' => null, 'npm_url' => 'https://www.npmjs.com/package/b']);
    // Excluded: neither link.
    consoleCmd_publishedProject(['slug' => 'neither', 'github_url' => null]);

    $this->artisan('projects:warm-readme-cache')
        ->expectsOutputToContain('Dispatched 2 README warm jobs.')
        ->assertSuccessful();

    Queue::assertPushed(WarmReadmeCacheJob::class, 2);
});

it('limits the warm run to a single slug via --slug', function () {
    consoleCmd_publishedProject(['slug' => 'keep', 'github_url' => 'https://github.com/owner/a']);
    consoleCmd_publishedProject(['slug' => 'skip', 'github_url' => 'https://github.com/owner/b']);

    $this->artisan('projects:warm-readme-cache', ['--slug' => 'keep'])
        ->expectsOutputToContain('Dispatched 1 README warm jobs.')
        ->assertSuccessful();

    Queue::assertPushed(WarmReadmeCacheJob::class, fn (WarmReadmeCacheJob $job) => $job->project->slug === 'keep');
});

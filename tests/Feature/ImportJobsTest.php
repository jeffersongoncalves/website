<?php

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Exceptions\GithubRateLimitException;
use App\Jobs\ImportGithubRepoJob;
use App\Jobs\ImportNpmPackageJob;
use App\Jobs\ImportWebsiteJob;
use App\Jobs\ImportYoutubeChannelJob;
use App\Jobs\RefreshProjectStatsJob;
use App\Models\Project;
use App\Support\ProjectImporter;
use App\Support\SiteStats;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush(); // ProjectImporter memoises per repo for an hour.
});

it('seeds a website row with the site- slug and is idempotent', function () {
    (new ImportWebsiteJob('https://example.com', 'Example Site'))->handle();

    $row = Project::query()->where('slug', 'site-example-site')->first();
    expect($row)->not->toBeNull()
        ->and($row->category)->toBe(ProjectCategory::Website)
        ->and($row->docs_url)->toBe('https://example.com')
        ->and($row->status)->toBe(ProjectStatus::Published);

    // Re-running does not duplicate.
    (new ImportWebsiteJob('https://example.com', 'Example Site'))->handle();
    expect(Project::query()->where('slug', 'site-example-site')->count())->toBe(1);
});

it('seeds a youtube channel with an encoded handle url', function () {
    (new ImportYoutubeChannelJob('MateusGuimarães', 'Mateus Guimarães'))->handle();

    $row = Project::query()->where('category', ProjectCategory::YoutubeChannel)->first();
    expect($row)->not->toBeNull()
        ->and($row->slug)->toBe('youtube-mateusguimaraes')
        // Non-ASCII handle is percent-encoded in the docs_url.
        ->and($row->docs_url)->toBe('https://www.youtube.com/@'.rawurlencode('MateusGuimarães'));
});

it('skips a github import when the repo is already cadastrado (no duplicate, no API call)', function () {
    Http::fake(); // any outbound call would throw — proves none happens

    Project::query()->create([
        'slug' => 'owner-repo',
        'name' => 'Repo',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/owner/repo',
        'published_at' => now(),
    ]);

    (new ImportGithubRepoJob('https://github.com/owner/repo'))->handle();

    expect(Project::query()->where('github_url', 'https://github.com/owner/repo')->count())->toBe(1);
    Http::assertNothingSent();
});

it('imports a new github repo into a published project', function () {
    Http::fake([
        'api.github.com/repos/acme/widget/branches*' => Http::response([]),
        'api.github.com/repos/acme/widget' => Http::response([
            'default_branch' => 'main', 'name' => 'widget', 'topics' => [],
            'description' => 'A widget', 'homepage' => null, 'language' => 'PHP',
            'license' => ['spdx_id' => 'MIT'],
        ]),
        'raw.githubusercontent.com/acme/widget/main/composer.json' => Http::response(['name' => 'acme/widget', 'description' => 'A widget']),
        'packagist.org/packages/acme/widget.json' => Http::response(['package' => ['repository' => 'https://github.com/acme/widget']]),
        'raw.githubusercontent.com/*' => Http::response('', 404),
    ]);

    (new ImportGithubRepoJob('https://github.com/acme/widget', 'awesome_list'))->handle();

    $row = Project::query()->where('github_url', 'https://github.com/acme/widget')->first();
    expect($row)->not->toBeNull()
        ->and($row->status)->toBe(ProjectStatus::Published)
        ->and($row->slug)->toBe('acme-widget');
});

it('skips an npm import when the package is already cadastrado', function () {
    Http::fake();

    Project::query()->create([
        'slug' => 'some-pkg',
        'name' => 'Some Pkg',
        'category' => ProjectCategory::JavascriptPackage,
        'status' => ProjectStatus::Published,
        'npm_url' => 'https://www.npmjs.com/package/some-pkg',
        'published_at' => now(),
    ]);

    (new ImportNpmPackageJob('some-pkg'))->handle();

    expect(Project::query()->where('npm_url', 'https://www.npmjs.com/package/some-pkg')->count())->toBe(1);
    Http::assertNothingSent();
});

it('throws a rate-limit exception from the importer instead of degrading to incomplete data', function () {
    // Swap a clean factory: the global beforeEach registers a permissive
    // `api.github.com/repos/*` 200 stub, and first-registered-stub wins, so a
    // later override would never be reached. The fresh factory has only our
    // rate-limit stub.
    Http::swap(new Factory);
    Http::fake([
        'api.github.com/*' => Http::response('', 403, [
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Reset' => (string) (time() + 90),
        ]),
    ]);

    ProjectImporter::fromGithub('https://github.com/acme/widget');
})->throws(GithubRateLimitException::class);

it('releases the github import job back to the queue when GitHub is rate-limiting, creating nothing', function () {
    Http::swap(new Factory);
    Http::fake([
        'api.github.com/*' => Http::response('', 403, [
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Reset' => (string) (time() + 120),
        ]),
    ]);

    // InteractsWithQueue::release() delegates to the underlying queue Job —
    // mock just that so the real job constructor/onQueue run untouched.
    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('release')->once();

    $job = (new ImportGithubRepoJob('https://github.com/acme/widget'))->setJob($queueJob);
    $job->handle();

    expect(Project::query()->where('github_url', 'https://github.com/acme/widget')->count())->toBe(0);
});

it('refreshes the derived site stats without error', function () {
    Project::query()->create([
        'slug' => 'a-plugin',
        'name' => 'A Plugin',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
    ]);

    (new RefreshProjectStatsJob)->handle();

    expect(SiteStats::all()['filament'])->toBeGreaterThanOrEqual(1);
});

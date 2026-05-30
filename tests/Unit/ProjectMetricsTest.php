<?php

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Exceptions\GithubRateLimitException;
use App\Models\Project;
use App\Support\ProjectMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function metricsProject(array $attributes = []): Project
{
    return Project::query()->create(array_merge([
        'name' => 'Repo',
        'slug' => 'repo',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/owner/repo',
        'stars' => 0,
        'downloads' => 0,
    ], $attributes));
}

it('reuses default_branch from the repo snapshot instead of refetching /repos', function () {
    // The post-save observer dispatches another sync — fake the queue so it
    // doesn't recurse on the sync driver and double the call counts.
    Queue::fake();
    Http::fake([
        'api.github.com/repos/owner/repo/branches*' => Http::response([['name' => '3.x'], ['name' => 'main']]),
        'api.github.com/repos/owner/repo/contributors*' => Http::response([]),
        'api.github.com/repos/owner/repo' => Http::response(['default_branch' => 'main', 'stargazers_count' => 5, 'topics' => []]),
    ]);

    // versions present so branch repair runs — previously that path issued a
    // second GET /repos just to read default_branch.
    ProjectMetrics::sync(metricsProject(['versions' => ['v3']]));

    $repoSnapshotCalls = 0;
    Http::assertSent(function ($request) use (&$repoSnapshotCalls) {
        if ($request->url() === 'https://api.github.com/repos/owner/repo') {
            $repoSnapshotCalls++;
        }

        return true;
    });

    expect($repoSnapshotCalls)->toBe(1);
});

it('caps contributors and branches at a single page', function () {
    Queue::fake();
    Http::fake([
        'api.github.com/repos/owner/repo/branches*' => Http::response([['name' => 'main']]),
        'api.github.com/repos/owner/repo/contributors*' => Http::response(
            array_map(fn ($i) => ['login' => "user{$i}", 'contributions' => 1], range(1, 100))
        ),
        'api.github.com/repos/owner/repo' => Http::response(['default_branch' => 'main', 'stargazers_count' => 1, 'topics' => []]),
    ]);

    ProjectMetrics::sync(metricsProject(['versions' => ['v3']]));

    // No page=2 fan-out on either paginated endpoint.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'page=2'));
});

it('throws GithubRateLimitException when GitHub reports the primary limit exhausted', function () {
    Http::fake([
        'api.github.com/*' => Http::response('', 403, [
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Reset' => (string) (time() + 120),
        ]),
    ]);

    expect(fn () => ProjectMetrics::sync(metricsProject()))
        ->toThrow(GithubRateLimitException::class);
});

it('does not treat an ordinary 403 as a rate limit', function () {
    Http::fake([
        'api.github.com/repos/owner/repo/contributors*' => Http::response([]),
        'api.github.com/repos/owner/repo' => Http::response('', 403, ['X-RateLimit-Remaining' => '42']),
    ]);

    // Forbidden-but-not-limited resolves to null inside the fetchers, so sync
    // completes without throwing.
    expect(ProjectMetrics::sync(metricsProject()))->toBeBool();
});

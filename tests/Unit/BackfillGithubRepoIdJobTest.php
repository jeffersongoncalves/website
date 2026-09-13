<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Jobs\BackfillGithubRepoIdJob;
use App\Models\Project;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

function backfillRepoIdProject(string $githubUrl): Project
{
    return Project::query()->create([
        'name' => 'Widget',
        'slug' => 'widget',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'github_url' => $githubUrl,
    ]);
}

beforeEach(function (): void {
    Http::swap(new Factory);
    Http::preventStrayRequests();
});

it('does nothing when the github_url has no parseable owner/repo slug', function () {
    $project = backfillRepoIdProject('not-a-github-url');

    (new BackfillGithubRepoIdJob($project))->handle();

    expect($project->fresh()->github_repo_id)->toBeNull();
});

it('sets github_repo_id and leaves github_url alone when it is already canonical', function () {
    $project = backfillRepoIdProject('https://github.com/acme/widget');

    Http::fake([
        'api.github.com/repos/acme/widget' => Http::response([
            'id' => 12345,
            'html_url' => 'https://github.com/acme/widget',
        ], 200),
    ]);

    (new BackfillGithubRepoIdJob($project))->handle();

    $project->refresh();
    expect($project->github_repo_id)->toBe(12345)
        ->and($project->github_url)->toBe('https://github.com/acme/widget');
});

it('updates github_url to the canonical lowercased html_url when it diverges', function () {
    $project = backfillRepoIdProject('https://github.com/Acme/Widget');

    Http::fake([
        'api.github.com/repos/*' => Http::response([
            'id' => 12345,
            'html_url' => 'https://github.com/acme/widget-renamed',
        ], 200),
    ]);

    (new BackfillGithubRepoIdJob($project))->handle();

    $project->refresh();
    expect($project->github_repo_id)->toBe(12345)
        ->and($project->github_url)->toBe('https://github.com/acme/widget-renamed');
});

it('releases the job when fetchRepo hits a github rate limit', function () {
    $project = backfillRepoIdProject('https://github.com/acme/widget');

    Http::fake([
        'api.github.com/repos/*' => Http::response('', 403, [
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Reset' => (string) (time() + 120),
        ]),
    ]);

    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('release')->once();

    (new BackfillGithubRepoIdJob($project))->setJob($queueJob)->handle();

    expect($project->fresh()->github_repo_id)->toBeNull();
});

it('stamps unavailable_at when the repo is confirmed gone (404)', function () {
    $project = backfillRepoIdProject('https://github.com/acme/ghost');

    Http::fake([
        'api.github.com/repos/*' => Http::response('', 404),
    ]);

    (new BackfillGithubRepoIdJob($project))->handle();

    expect($project->fresh()->unavailable_at)->not->toBeNull();
});

it('leaves github_repo_id null and logs a warning when the repo status is unknown', function () {
    $project = backfillRepoIdProject('https://github.com/acme/flaky');

    Http::fake([
        'api.github.com/repos/*' => Http::response('', 500),
    ]);

    (new BackfillGithubRepoIdJob($project))->handle();

    $project->refresh();
    expect($project->github_repo_id)->toBeNull()
        ->and($project->unavailable_at)->toBeNull();
});

it('releases the job when repoStatus hits a github rate limit on the unresolvable path', function () {
    $project = backfillRepoIdProject('https://github.com/acme/widget');

    Http::fake(function ($request) {
        // fetchRepo (returns an unresolvable payload) then repoStatus (rate-limited).
        if (str_contains($request->url(), 'api.github.com/repos/acme/widget')) {
            static $calls = 0;
            $calls++;

            return $calls === 1
                ? Http::response(['id' => null, 'html_url' => null], 200)
                : Http::response('', 403, ['X-RateLimit-Remaining' => '0', 'X-RateLimit-Reset' => (string) (time() + 60)]);
        }

        return Http::response('', 404);
    });

    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('release')->once();

    (new BackfillGithubRepoIdJob($project))->setJob($queueJob)->handle();

    expect($project->fresh()->github_repo_id)->toBeNull();
});

it('logs context when BackfillGithubRepoIdJob fails', function () {
    $project = backfillRepoIdProject('https://github.com/acme/widget');

    Log::shouldReceive('error')
        ->once()
        ->with('BackfillGithubRepoIdJob failed', Mockery::on(fn ($ctx) => $ctx['project'] === 'widget' && $ctx['error'] === 'boom'));

    (new BackfillGithubRepoIdJob($project))->failed(new RuntimeException('boom'));
});

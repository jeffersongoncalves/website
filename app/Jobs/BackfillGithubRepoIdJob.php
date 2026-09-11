<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Project;
use App\Support\GithubQuota;
use App\Support\GithubReadme;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use JeffersonGoncalves\GitHubClient\Exceptions\GitHubRateLimitException;
use JeffersonGoncalves\GitHubClient\GitHubClient;
use Throwable;

/**
 * One-time backfill: resolves the numeric github_repo_id for a project and,
 * only when it diverges (case-insensitive), updates github_url to the
 * canonical (lowercased) html_url GitHub returns — following a 301 for a
 * renamed/transferred repo picks up the NEW slug here, which is what
 * MergeDuplicateProjects groups duplicates by. Every actual URL change is
 * logged individually ("github_url updated to canonical html_url") — grep
 * the log for that line to count how many rows a backfill run touched.
 *
 * A confirmed 404 is left alone here beyond stamping unavailable_at (see
 * handleUnresolvable()) — this job does not prune rows.
 */
final class BackfillGithubRepoIdJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $backoff = 30;

    public int $tries = 0;

    // fetchRepo() — a single REST call, following any redirect transparently.
    public const COST = 1;

    public function __construct(public Project $project, public int $staggerSeconds = 0)
    {
        $this->onQueue('github')->onConnection('redis-github');
    }

    public static function make(Project $project): static
    {
        $delay = GithubQuota::reserve(self::COST);

        return (new self($project, $delay))->delay($delay);
    }

    public static function enqueue(Project $project): void
    {
        dispatch(self::make($project));
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addSeconds($this->staggerSeconds)->addHour();
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("backfill-github-repo-id:{$this->project->getKey()}"))->dontRelease(),
            new RateLimited('github-api'),
        ];
    }

    public function handle(): void
    {
        $slug = GithubReadme::repoFromUrl($this->project->github_url);

        if ($slug === null) {
            return;
        }

        try {
            $repo = GitHubClient::fetchRepo($slug);
        } catch (GitHubRateLimitException $e) {
            $this->release($e->retryAfter);

            return;
        }

        $id = $repo['id'] ?? null;
        $htmlUrl = $repo['html_url'] ?? null;

        if (! is_int($id) || $id <= 0 || ! is_string($htmlUrl) || $htmlUrl === '') {
            $this->handleUnresolvable($slug);

            return;
        }

        $canonicalUrl = strtolower($htmlUrl);
        $urlChanged = strcasecmp((string) $this->project->github_url, $canonicalUrl) !== 0;

        $this->project->github_repo_id = $id;

        if ($urlChanged) {
            Log::info('BackfillGithubRepoIdJob: github_url updated to canonical html_url', [
                'project' => $this->project->slug,
                'old' => $this->project->github_url,
                'new' => $canonicalUrl,
            ]);
            $this->project->github_url = $canonicalUrl;
        }

        $this->project->saveQuietly();
    }

    /**
     * fetchRepo() can't tell a confirmed 404 apart from a transient failure —
     * only GitHubClient::repoStatus() does that (a second REST call, made
     * only on this already-rare path). A 404 is stamped unavailable_at; a
     * transient/unknown failure is left alone so the next backfill run
     * (still targeting whereNull('github_repo_id')) retries it.
     */
    private function handleUnresolvable(string $slug): void
    {
        try {
            $status = GitHubClient::repoStatus($slug);
        } catch (GitHubRateLimitException $e) {
            $this->release($e->retryAfter);

            return;
        }

        if ($status === GitHubClient::REPO_GONE) {
            $this->project->unavailable_at = now();
            $this->project->saveQuietly();

            Log::info('BackfillGithubRepoIdJob: repo confirmed gone (404), hidden from public listings', [
                'project' => $this->project->slug,
                'github_url' => $this->project->github_url,
            ]);

            return;
        }

        Log::warning('BackfillGithubRepoIdJob: repo unresolvable, leaving github_repo_id null', [
            'project' => $this->project->slug,
            'github_url' => $this->project->github_url,
            'status' => $status,
        ]);
    }

    public function failed(?Throwable $e): void
    {
        Log::error('BackfillGithubRepoIdJob failed', [
            'project' => $this->project->slug,
            'error' => $e?->getMessage(),
        ]);
    }
}

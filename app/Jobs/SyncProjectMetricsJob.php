<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Project;
use App\Support\GithubQuota;
use App\Support\ProjectMetrics;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use JeffersonGoncalves\GitHubClient\Exceptions\GitHubRateLimitException;
use Throwable;

final class SyncProjectMetricsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $backoff = 30;

    // 0 = unlimited attempts. Without this the job inherits the worker/Horizon
    // tries and a rate-limit release trips MaxAttemptsExceededException at the
    // inherited limit; retryUntil() below bounds the retries by time instead.
    public int $tries = 0;

    // REST calls made by ProjectMetrics::sync(): /contributors only (the repo
    // snapshot goes through /graphql, which doesn't count against this quota).
    // Worst-case, não medido via GET /rate_limit.
    public const COST = 1;

    public function __construct(public Project $project, public int $staggerSeconds = 0)
    {
        $this->onQueue('github');
    }

    /**
     * Reserves this job's github-api quota slot and returns an instance
     * already delayed to that slot. Every dispatch path (single enqueue() or
     * a Bus::batch()) MUST go through this — it's the only place the quota is
     * actually spent.
     */
    public static function make(Project $project): static
    {
        $delay = GithubQuota::reserve(self::COST);

        return (new self($project, $delay))->delay($delay);
    }

    public static function enqueue(Project $project): void
    {
        dispatch(static::make($project));
    }

    /**
     * Time-based retry window instead of a fixed attempt count: a GitHub rate
     * limit releases the job repeatedly, and each release would otherwise
     * burn an attempt and fail the job while it was only waiting for the
     * window to reset. 1h slack (one quota-window reset) is enough because
     * GithubQuota::reserve already paces dispatch to stay inside the budget.
     */
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
            (new WithoutOverlapping("project-metrics:{$this->project->getKey()}"))->dontRelease(),
            new RateLimited('github-api'),
        ];
    }

    public function handle(): void
    {
        try {
            ProjectMetrics::sync($this->project);

            // Warm the README cache with the just-synced version/branch data
            // so the first real visitor after this sync gets a disk read
            // instead of a cold GitHub fetch + render + sanitize.
            WarmReadmeCacheJob::enqueue($this->project);
        } catch (GitHubRateLimitException $e) {
            // Limit won't clear until the window resets — release with a delay
            // until then instead of retrying immediately and 403-ing again.
            // Keeps the log clean (no 200+ identical warnings per burst).
            $this->release($e->retryAfter);
        } catch (Throwable $e) {
            Log::warning('SyncProjectMetricsJob failed', [
                'project' => $this->project->slug,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('SyncProjectMetricsJob permanently failed', [
            'project' => $this->project->slug,
            'error' => $e?->getMessage(),
        ]);
    }
}

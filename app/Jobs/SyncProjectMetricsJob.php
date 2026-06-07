<?php

namespace App\Jobs;

use App\Exceptions\GithubRateLimitException;
use App\Models\Project;
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
use Throwable;

class SyncProjectMetricsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $backoff = 30;

    // 0 = unlimited attempts. Without this the job inherits the worker/Horizon
    // tries and a rate-limit release trips MaxAttemptsExceededException at the
    // inherited limit; retryUntil() below bounds the retries by time instead.
    public int $tries = 0;

    public function __construct(public Project $project)
    {
        $this->onQueue('github');
    }

    /**
     * Time-based retries instead of a fixed attempt count: a GitHub rate limit
     * releases the job repeatedly, and each release would otherwise burn an
     * attempt and fail the job while it was only waiting for the window to
     * reset. retryUntil keeps it retrying until the deadline.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(2);
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
        } catch (GithubRateLimitException $e) {
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
}

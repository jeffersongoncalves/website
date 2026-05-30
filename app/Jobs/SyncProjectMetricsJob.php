<?php

namespace App\Jobs;

use App\Exceptions\GithubRateLimitException;
use App\Models\Project;
use App\Support\ProjectMetrics;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncProjectMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public Project $project)
    {
        $this->onQueue('github');
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("project-metrics:{$this->project->getKey()}"))->dontRelease()];
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

<?php

namespace App\Jobs;

use App\Models\Project;
use App\Support\ProjectImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Re-verify one project's stored packagist_url against its github_url and drop
 * the link only when Packagist definitively disowns it (a 404, or a repository
 * pointing at a different repo). A transient/unknown result (rate limit, network
 * error, missing repository) releases the job to retry later — it must never
 * purge a valid link just because Packagist was unreachable.
 */
class PurgeMisattributedPackagistUrlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $backoff = 60;

    // 0 = unlimited attempts. Without this the job inherits the worker/Horizon
    // tries (3) and a rate-limit release trips MaxAttemptsExceededException;
    // retryUntil() below is what actually bounds the retries (by time).
    public int $tries = 0;

    public function __construct(public int $projectId) {}

    /**
     * Time-based retries so a Packagist rate limit (UNKNOWN → release) doesn't
     * exhaust a fixed attempt budget before the window resets — see
     * SyncProjectMetricsJob::retryUntil for the rationale.
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
            (new WithoutOverlapping("packagist-verify:{$this->projectId}"))->dontRelease(),
            new RateLimited('packagist-api'),
        ];
    }

    public function handle(): void
    {
        $project = Project::query()->find($this->projectId);

        if ($project === null || $project->packagist_url === null || $project->github_url === null) {
            return;
        }

        $status = ProjectImporter::packagistUrlOwnershipStatus(
            (string) $project->packagist_url,
            (string) $project->github_url,
        );

        if ($status === ProjectImporter::PACKAGIST_UNKNOWN) {
            // Couldn't verify (likely a rate limit) — try again shortly rather
            // than risk purging a valid link.
            $this->release($this->backoff);

            return;
        }

        if ($status === ProjectImporter::PACKAGIST_FOREIGN) {
            $was = $project->packagist_url;
            // Drop the link AND the download metrics it produced — those counts
            // were pulled from the wrong package (e.g. laravel/laravel's millions
            // of installs). The next projects:sync-metrics run refetches the
            // correct numbers (none, now that packagist_url is gone).
            // Per-record update so ProjectObserver flushes caches / site stats.
            $project->update([
                'packagist_url' => null,
                'downloads' => 0,
                'downloads_label' => null,
            ]);

            Log::info('Purged mis-attributed packagist_url and its download metrics', [
                'project' => $project->slug,
                'was' => $was,
                'github' => $project->github_url,
            ]);
        }
    }
}

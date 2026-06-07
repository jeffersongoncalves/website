<?php

namespace App\Jobs;

use App\Enums\PackageType;
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
use Throwable;

/**
 * Re-verify one project's stored packagist_url AND npm_url against its
 * github_url and drop whichever the registry definitively disowns (a 404, or a
 * repository pointing at a different repo). A transient/unknown result (rate
 * limit, network error, missing repository) releases the job to retry later —
 * it must never purge a valid link just because a registry was unreachable.
 */
class PurgeMisattributedPackageLinksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $backoff = 60;

    // 0 = unlimited attempts. Without this the job inherits the worker/Horizon
    // tries (3) and a rate-limit release trips MaxAttemptsExceededException;
    // retryUntil() below is what actually bounds the retries (by time).
    public int $tries = 0;

    public function __construct(public int $projectId) {}

    /**
     * Time-based retries so a registry rate limit (UNKNOWN → release) doesn't
     * exhaust a fixed attempt budget before the window resets.
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
            (new WithoutOverlapping("package-links-verify:{$this->projectId}"))->dontRelease(),
            new RateLimited('packagist-api'),
        ];
    }

    public function handle(): void
    {
        $project = Project::query()->find($this->projectId);

        if ($project === null || $project->github_url === null) {
            return;
        }

        if ($project->packagist_url === null && $project->npm_url === null) {
            return;
        }

        $githubUrl = (string) $project->github_url;

        $packagist = $project->packagist_url !== null
            ? ProjectImporter::packagistUrlOwnershipStatus((string) $project->packagist_url, $githubUrl)
            : null;

        $npm = $project->npm_url !== null
            ? ProjectImporter::npmUrlOwnershipStatus((string) $project->npm_url, $githubUrl)
            : null;

        // Couldn't verify a present link (likely a rate limit) — retry the whole
        // job shortly rather than risk purging a valid one.
        if ($packagist === ProjectImporter::LINK_UNKNOWN || $npm === ProjectImporter::LINK_UNKNOWN) {
            $this->release($this->backoff);

            return;
        }

        $updates = [];

        if ($packagist === ProjectImporter::LINK_FOREIGN) {
            $updates['packagist_url'] = null;
            if ($project->package_type === PackageType::Composer) {
                $updates['package_type'] = PackageType::None;
            }
        }

        if ($npm === ProjectImporter::LINK_FOREIGN) {
            $updates['npm_url'] = null;
            if ($project->package_type === PackageType::Npm) {
                $updates['package_type'] = PackageType::None;
            }
        }

        if ($updates === []) {
            return;
        }

        // A purged link means its download metrics were pulled from the wrong
        // package — reset them; the next projects:sync-metrics refetches.
        $updates['downloads'] = 0;
        $updates['downloads_label'] = null;

        // Per-record update so ProjectObserver flushes caches / site stats.
        $project->update($updates);

        Log::info('Purged mis-attributed package links', [
            'project' => $project->slug,
            'github' => $githubUrl,
            'purged' => array_keys($updates),
        ]);
    }

    public function failed(?Throwable $e): void
    {
        Log::error('PurgeMisattributedPackageLinksJob failed', [
            'project_id' => $this->projectId,
            'error' => $e?->getMessage(),
        ]);
    }
}

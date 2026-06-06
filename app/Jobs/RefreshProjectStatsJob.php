<?php

namespace App\Jobs;

use App\Support\SiteStats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Recompute the SiteStat singleton's locally-derived columns (counts/badges)
 * once, after a project change or a bulk import. refreshProjectDerived() fires
 * ~30 aggregate queries, so calling it inline on every Project save (as the
 * observer used to) means a bulk import of hundreds of repos recomputes the
 * whole breakdown hundreds of times on the worker path.
 *
 * This job debounces that. ShouldBeUniqueUntilProcessing holds a dispatch-time
 * lock (keyed by uniqueId) from the moment it is queued until it *starts*
 * processing, so every dispatch landing inside the delay window collapses into
 * the single already-queued job. The trailing edge still fires: a save arriving
 * after the job begins processing starts a fresh window, so the last change is
 * never lost. WithoutOverlapping additionally guards against two recomputes
 * writing the singleton concurrently.
 */
class RefreshProjectStatsJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * Max seconds the dispatch-time unique lock is held before it is
     * auto-released, in case the job is lost before it starts processing.
     */
    public int $uniqueFor = 120;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'site-stats:refresh-derived';
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('site-stats:refresh-derived'))->dontRelease()];
    }

    public function handle(): void
    {
        SiteStats::refreshProjectDerived();
    }

    public function failed(?Throwable $e): void
    {
        Log::error('RefreshProjectStatsJob failed', [
            'error' => $e?->getMessage(),
        ]);
    }
}

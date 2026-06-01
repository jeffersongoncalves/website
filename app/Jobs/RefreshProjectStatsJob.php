<?php

namespace App\Jobs;

use App\Support\SiteStats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/**
 * Recompute the SiteStat singleton's locally-derived columns (counts/badges)
 * once, after a bulk import. Bulk ops fan out hundreds of import jobs; calling
 * SiteStats::refreshProjectDerived() inside each would recompute the whole
 * breakdown hundreds of times. Instead the op dispatches a single one of these
 * with a delay scaled to the batch size, so the recompute lands after the
 * import jobs have drained. WithoutOverlapping collapses any duplicates.
 */
class RefreshProjectStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct()
    {
        $this->onQueue('default');
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
}

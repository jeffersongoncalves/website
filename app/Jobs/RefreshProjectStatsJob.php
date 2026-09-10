<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\SiteStats;

/**
 * Recompute the SiteStat singleton's locally-derived columns (counts/badges)
 * once, after a project change or a bulk import. refreshProjectDerived() fires
 * ~30 aggregate queries, so calling it inline on every Project save (as the
 * observer used to) means a bulk import of hundreds of repos recomputes the
 * whole breakdown hundreds of times on the worker path — see DebouncedJob for
 * how the debounce itself works.
 */
class RefreshProjectStatsJob extends DebouncedJob
{
    protected int $expireAfter = 180;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'site-stats:refresh-derived';
    }

    public function handle(): void
    {
        SiteStats::refreshProjectDerived();
    }
}

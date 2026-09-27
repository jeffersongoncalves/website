<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use Illuminate\View\View;

class OfflineController
{
    /**
     * Tiny fallback page served by the service worker when a navigation
     * fails (no network + nothing in cache). Kept deliberately static —
     * no DB queries, no external HTTP — so it renders even when every
     * other route is unreachable.
     */
    public function __invoke(): View
    {
        // The service worker precaches this page's HTML only. With BladeWind's
        // default `link` delivery its per-page stylesheet would be a separate
        // /bladewind file that is never cached for an offline hit, so embed it.
        config(['bladewind.pages.delivery' => 'inline']);

        return view('site.offline');
    }
}

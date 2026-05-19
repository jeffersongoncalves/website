<?php

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
        return view('site.offline');
    }
}

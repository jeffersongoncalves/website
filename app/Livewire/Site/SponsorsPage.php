<?php

declare(strict_types=1);

namespace App\Livewire\Site;

use App\Support\SiteStats;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Full-page Livewire component for /sponsors. Static content (no wire actions),
 * so the route stays in the page cache — there are no /livewire/update requests
 * that a cached CSRF token could break.
 */
class SponsorsPage extends Component
{
    public function render(): View
    {
        return view('livewire.site.sponsors-page', [
            // Already synced by the scheduled projects:sync-metrics command
            // (SiteStats::compute() -> public_sponsors) — read-only here, no
            // extra GitHub call.
            'sponsorCount' => SiteStats::all()['public_sponsors'],
        ])
            ->layout('components.site.layouts.app', [
                'title' => __('site.nav.sponsors'),
                'description' => __('site.seo.sponsors'),
            ]);
    }
}

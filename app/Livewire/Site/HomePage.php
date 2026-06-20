<?php

declare(strict_types=1);

namespace App\Livewire\Site;

use App\Models\Project;
use App\Support\SiteStats;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Full-page Livewire component for the homepage. Static content (no wire
 * actions), so the route stays in the page cache.
 */
class HomePage extends Component
{
    public function render(): View
    {
        $featured = Project::query()
            ->published()
            ->featured()
            ->orderByDesc('stars')
            ->orderBy('name')
            ->take(6)
            ->get();

        return view('livewire.site.home-page', [
            'featured' => $featured,
            'homeStats' => SiteStats::homeCards(),
            'stack' => config('site.stack'),
            'contributions' => SiteStats::contributions(),
        ])->layout('components.site.layouts.app', [
            'title' => __('site.home.hero_l1'),
            'description' => __('site.seo.home'),
        ]);
    }
}

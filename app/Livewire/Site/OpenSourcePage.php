<?php

namespace App\Livewire\Site;

use App\Models\Project;
use App\Support\SiteStats;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Full-page Livewire component for /open-source. Static content (no wire
 * actions), so the route stays in the page cache.
 */
class OpenSourcePage extends Component
{
    public function render(): View
    {
        $topRepos = Project::query()
            ->published()
            ->orderByDesc('stars')
            ->take(6)
            ->get();

        return view('livewire.site.open-source-page', [
            'osStats' => SiteStats::osCards(),
            'topRepos' => $topRepos,
            'contributions' => SiteStats::contributions(),
            'reposCount' => SiteStats::all()['repos'],
        ])->layout('components.site.layouts.app', [
            'title' => __('site.os.page_title'),
            'description' => __('site.seo.open_source'),
        ]);
    }
}

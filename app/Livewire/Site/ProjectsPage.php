<?php

declare(strict_types=1);

namespace App\Livewire\Site;

use App\Support\SiteStats;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Full-page Livewire component for /projects. Holds the static hero (count
 * cards) and the contribute CTA; the interactive catalogue lives in the nested
 * <livewire:site.projects-list>. Route is excluded from CachePublicPage so the
 * list's wire requests get a fresh CSRF token.
 */
class ProjectsPage extends Component
{
    public function render(): View
    {
        $stats = SiteStats::all();

        $counts = [
            'total' => $stats['repos'],
            'catalogue' => $stats['catalogue'],
            'filament' => $stats['filament'],
            'laravel' => $stats['laravel'],
            'starter' => $stats['starter'],
            'tool' => $stats['tool'],
            'maintained' => $stats['maintained'],
            'daily_drivers' => $stats['daily_drivers'],
        ];

        return view('livewire.site.projects-page', ['counts' => $counts])
            ->layout('components.site.layouts.app', [
                'title' => __('site.projects.title'),
                'description' => __('site.seo.projects'),
            ]);
    }
}

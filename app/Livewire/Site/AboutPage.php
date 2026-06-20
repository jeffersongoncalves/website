<?php

declare(strict_types=1);

namespace App\Livewire\Site;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Full-page Livewire component for /about. Static content (no wire actions), so
 * the route stays in the page cache.
 */
class AboutPage extends Component
{
    public function render(): View
    {
        return view('livewire.site.about-page', [
            'timeline' => config('site.timeline'),
            'education' => config('site.education'),
            'principles' => config('site.principles'),
        ])->layout('components.site.layouts.app', [
            'title' => __('site.about.title_1').' '.__('site.about.title_2'),
            'description' => __('site.seo.about'),
        ]);
    }
}

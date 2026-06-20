<?php

declare(strict_types=1);

namespace App\Livewire\Site;

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
        return view('livewire.site.sponsors-page')
            ->layout('components.site.layouts.app', [
                'title' => __('site.nav.sponsors'),
                'description' => __('site.seo.sponsors'),
            ]);
    }
}

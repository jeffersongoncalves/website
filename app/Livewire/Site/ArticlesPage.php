<?php

namespace App\Livewire\Site;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Full-page Livewire component for /articles. Renders the static hero and
 * embeds <livewire:site.articles-list> for the paginated list. The route is
 * excluded from CachePublicPage so the nested component's wire requests get a
 * fresh CSRF token (a shared cached one would 419).
 */
class ArticlesPage extends Component
{
    public function render(): View
    {
        return view('livewire.site.articles-page')
            ->layout('components.site.layouts.app', [
                'title' => __('site.articles.title'),
                'description' => __('site.articles.sub'),
            ]);
    }
}

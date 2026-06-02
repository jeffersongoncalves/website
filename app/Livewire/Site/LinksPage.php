<?php

namespace App\Livewire\Site;

use App\Enums\ProjectCategory;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Full-page Livewire component for the /links hub. Decides which external-link
 * sections have published rows (so a section keeps its filter bar even when the
 * current filter empties it) and renders one <livewire:site.links-section> per
 * type. Each section keeps its own search/sort/topic/page state, keyed by its
 * anchor, so filtering one never disturbs another.
 */
class LinksPage extends Component
{
    public function render(): View
    {
        $sections = [];

        foreach (ProjectCategory::externalLinkCases() as $cat) {
            if (Project::query()->published()->byCategory($cat)->exists()) {
                $sections[] = $cat;
            }
        }

        return view('livewire.site.links-page', ['sections' => $sections])
            ->layout('components.site.layouts.app', [
                'title' => __('site.links.title'),
                'description' => __('site.seo.links'),
            ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Livewire\Site;

use App\Enums\ProjectCategory;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
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
    public const SECTIONS_CACHE_KEY = 'links_sections';

    public function render(): View
    {
        // Which external-link categories have at least one published row. Only
        // changes on a project save (ProjectObserver flushes this key), so cache
        // it instead of running one exists() per category on every render.
        $values = Cache::rememberForever(self::SECTIONS_CACHE_KEY, function (): array {
            $sections = [];

            foreach (ProjectCategory::externalLinkCases() as $cat) {
                if (Project::query()->published()->byCategory($cat)->exists()) {
                    $sections[] = $cat->value;
                }
            }

            return $sections;
        });

        $sections = array_map(fn (string $v): ProjectCategory => ProjectCategory::from($v), $values);

        return view('livewire.site.links-page', ['sections' => $sections])
            ->layout('components.site.layouts.app', [
                'title' => __('site.links.title'),
                'description' => __('site.seo.links'),
            ]);
    }
}

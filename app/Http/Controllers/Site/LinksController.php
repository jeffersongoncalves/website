<?php

namespace App\Http\Controllers\Site;

use App\Enums\ProjectCategory;
use App\Models\Project;
use Illuminate\Contracts\View\View;

/**
 * The /links hub — external reference links (sites, YouTube channels, learning
 * resources, awesome lists) pulled out of the code catalogue at /projects.
 * Each type renders as its own section with its own independent paginator, so
 * a long Sites list can page without disturbing the YouTube list.
 */
class LinksController
{
    private const PER_PAGE = 6;

    public function __invoke(): View
    {
        // One paginator per external-link type, each on its own page query
        // param (so ?sites=2 doesn't move the YouTube list). External links
        // carry no stars/downloads, so order alphabetically. Sections render
        // in the curated externalLinkCases() order; empty types are dropped.
        $sections = [];

        foreach (ProjectCategory::externalLinkCases() as $cat) {
            // The section anchor doubles as the paginator page name, so ?sites=2
            // pages the Sites group without touching the others.
            $anchor = $cat->linksSection() ?? $cat->value;

            $projects = Project::query()
                ->published()
                ->byCategory($cat)
                ->orderBy('name')
                ->paginate(self::PER_PAGE, ['*'], $anchor)
                ->withQueryString();

            if ($projects->total() > 0) {
                // Anchor the pagination links to the section so paging a group
                // lands back on it instead of scrolling to the top of /links.
                $projects->fragment($anchor);

                $sections[] = ['category' => $cat, 'projects' => $projects, 'anchor' => $anchor];
            }
        }

        return view('site.links.index', ['sections' => $sections]);
    }
}

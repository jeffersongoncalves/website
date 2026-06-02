<?php

namespace App\Livewire\Site;

use App\Enums\ProjectCategory;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Paginated list of published articles, newest first. Pagination runs through
 * Livewire (in-place morph, no full reload) using the site-styled paginator.
 */
class ArticlesList extends Component
{
    use WithPagination;

    public function paginationView(): string
    {
        return 'pagination.site-livewire';
    }

    public function render(): View
    {
        $articles = Project::query()
            ->published()
            ->byCategory(ProjectCategory::Article)
            ->orderByDesc('published_at')
            ->paginate(12);

        return view('livewire.site.articles-list', ['articles' => $articles]);
    }
}

<?php

namespace App\Http\Controllers\Site;

use App\Enums\ProjectCategory;
use App\Models\Project;
use Illuminate\Contracts\View\View;

class ArticlesController
{
    public function __invoke(): View
    {
        $articles = Project::query()
            ->published()
            ->byCategory(ProjectCategory::Article)
            ->orderByDesc('published_at')
            ->paginate(12)
            // Anchor pagination to the list so paging doesn't jump to the top.
            ->fragment('articles');

        return view('site.articles.index', ['articles' => $articles]);
    }
}

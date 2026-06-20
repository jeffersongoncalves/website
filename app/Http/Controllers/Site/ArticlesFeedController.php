<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Enums\ProjectCategory;
use App\Models\Project;
use Illuminate\Http\Response;

class ArticlesFeedController
{
    public function __invoke(): Response
    {
        $articles = Project::query()
            ->published()
            ->byCategory(ProjectCategory::Article)
            ->orderByDesc('published_at')
            ->limit(50)
            ->get();

        return response()
            ->view('site.articles.feed', ['articles' => $articles])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}

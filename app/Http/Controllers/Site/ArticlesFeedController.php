<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Support\ArticlesFeedGenerator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * /articles/feed — serves the locale-matching file written to storage/app by
 * `articles-feed:generate` (daily cron + on project change via
 * GenerateArticlesFeedJob). storage/app, unlike public/, survives an atomic
 * deploy's release swap, and a plain file read keeps this route cheap (no DB
 * query + view render per request).
 */
class ArticlesFeedController
{
    public function __invoke(): Response
    {
        $xml = Storage::disk('local')->get(ArticlesFeedGenerator::storageKey(app()->getLocale()));

        abort_if($xml === null, SymfonyResponse::HTTP_NOT_FOUND);

        return response($xml)->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}

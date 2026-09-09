<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ProjectCategory;
use App\Models\Project;
use Illuminate\Support\Facades\App;

/**
 * Builds the /articles/feed RSS body, one variant per supported locale (feed
 * title/description and each article's translated title all depend on
 * app()->getLocale()). Shared by the `articles-feed:generate` command (writes
 * each variant to storage/app, which — unlike public/ — survives an atomic
 * deploy's release swap) and ArticlesFeedController, which just serves the
 * file matching the current request's locale.
 */
final class ArticlesFeedGenerator
{
    /**
     * @return array<string, string> locale => rendered RSS XML
     */
    public static function buildAll(): array
    {
        $articles = Project::query()
            ->published()
            ->byCategory(ProjectCategory::Article)
            ->orderByDesc('published_at')
            ->limit(50)
            ->get();

        $original = App::getLocale();

        try {
            $variants = [];

            foreach (config('locale-cookie.supported', ['pt_BR', 'en', 'es']) as $locale) {
                App::setLocale($locale);

                $variants[$locale] = view('site.articles.feed', ['articles' => $articles])->render();
            }

            return $variants;
        } finally {
            App::setLocale($original);
        }
    }

    public static function storageKey(string $locale): string
    {
        return "articles-feed-{$locale}.xml";
    }
}

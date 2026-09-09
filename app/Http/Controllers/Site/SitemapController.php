<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Enums\ProjectCategory;
use App\Models\Project;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * /sitemap.xml — built on the fly and cached forever (same pattern as
 * LlmsTxtController), invalidated by ProjectObserver on any project change.
 * Previously this was a static public/sitemap.xml file written by a daily
 * cron + queued job (`sitemap:generate`), which 404'd whenever a deploy
 * swapped in a new release directory before the next cron run — public/ is
 * per-release, not shared across releases like storage/ or the cache.
 */
class SitemapController
{
    public const CACHE_KEY = 'sitemap_xml';

    public function __invoke(): Response
    {
        $xml = Cache::rememberForever(self::CACHE_KEY, fn (): string => $this->build());

        return response($xml)->header('Content-Type', 'application/xml; charset=utf-8');
    }

    private function build(): string
    {
        $sitemap = Sitemap::create();

        $this->addPages($sitemap);
        $this->addProjects($sitemap);

        return $sitemap->render();
    }

    private function addPages(Sitemap $sitemap): void
    {
        $now = now();

        // Each static page carries a changefreq + priority so crawlers can
        // budget their re-crawls: the catalogue indexes change often and rank
        // highest; the evergreen pages (about/stack/sponsors) change rarely.
        $pages = [
            ['home', Url::CHANGE_FREQUENCY_DAILY, 1.0],
            ['projects.index', Url::CHANGE_FREQUENCY_DAILY, 0.9],
            ['articles.index', Url::CHANGE_FREQUENCY_DAILY, 0.9],
            ['links.index', Url::CHANGE_FREQUENCY_WEEKLY, 0.8],
            ['open-source', Url::CHANGE_FREQUENCY_WEEKLY, 0.8],
            ['about', Url::CHANGE_FREQUENCY_MONTHLY, 0.6],
            ['stack', Url::CHANGE_FREQUENCY_MONTHLY, 0.6],
            ['sponsors', Url::CHANGE_FREQUENCY_MONTHLY, 0.5],
            ['developers.mcp', Url::CHANGE_FREQUENCY_MONTHLY, 0.4],
        ];

        foreach ($pages as [$route, $frequency, $priority]) {
            $sitemap->add(
                Url::create(route($route))
                    ->setLastModificationDate($now)
                    ->setChangeFrequency($frequency)
                    ->setPriority($priority)
            );
        }
    }

    private function addProjects(Sitemap $sitemap): void
    {
        Project::query()->published()->orderBy('slug')->get(['slug', 'category', 'updated_at'])->each(
            function (Project $project) use ($sitemap): void {
                // Emit each project's canonical section URL (articles → /articles,
                // external links → /links, code → /projects) so the sitemap never
                // lists a link that just 301s elsewhere. Priority/changefreq are
                // tiered by kind: code projects (own packages) rank above curated
                // articles and the third-party external-link catalogue.
                [$priority, $frequency] = match (true) {
                    $project->category === ProjectCategory::Article => [0.6, Url::CHANGE_FREQUENCY_MONTHLY],
                    $project->category->isExternalLink() => [0.5, Url::CHANGE_FREQUENCY_MONTHLY],
                    default => [0.7, Url::CHANGE_FREQUENCY_WEEKLY],
                };

                $sitemap->add(
                    Url::create(route($project->canonicalRouteName(), ['slug' => $project->slug]))
                        ->setLastModificationDate($project->updated_at ?? now())
                        ->setChangeFrequency($frequency)
                        ->setPriority($priority)
                );
            }
        );
    }
}

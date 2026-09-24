<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ProjectCategory;
use App\Models\Project;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * Builds the /sitemap.xml body. Shared by the `sitemap:generate` command
 * (writes it to storage/app, which — unlike public/ — survives an atomic
 * deploy's release swap) and SitemapController's on-the-fly fallback.
 */
final class SitemapGenerator
{
    public static function build(): string
    {
        $sitemap = Sitemap::create();

        self::addPages($sitemap);
        self::addProjects($sitemap);

        return $sitemap->render();
    }

    private static function addPages(Sitemap $sitemap): void
    {
        // Each static page carries a changefreq + priority so crawlers can
        // budget their re-crawls: the catalogue indexes change often and rank
        // highest; the evergreen pages (about/stack/sponsors) change rarely.
        // No <lastmod>: a generation-time stamp on every run is a lie Google
        // learns to ignore sitewide, so these pages simply omit it.
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

        foreach (self::locales() as $locale) {
            foreach ($pages as [$route, $frequency, $priority]) {
                $sitemap->add(
                    self::localizedUrl($route, $locale)
                        ->setChangeFrequency($frequency)
                        ->setPriority($priority)
                );
            }
        }
    }

    private static function addProjects(Sitemap $sitemap): void
    {
        // Third-party stars are noindex (Project::isIndexable/getDynamicSEOData) —
        // thin mirrors of READMEs already on GitHub — so they stay out of the
        // sitemap and the crawl budget goes to own/curated pages.
        Project::query()->published()->indexable()->orderBy('slug')->get(['slug', 'category', 'updated_at'])->each(
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

                foreach (self::locales() as $locale) {
                    $url = self::localizedUrl($project->canonicalRouteName(), $locale, ['slug' => $project->slug])
                        ->setChangeFrequency($frequency)
                        ->setPriority($priority);

                    if ($project->updated_at !== null) {
                        $url->setLastModificationDate($project->updated_at);
                    }

                    $sitemap->add($url);
                }
            }
        );
    }

    /**
     * The `$locale` URL of a route plus hreflang alternates for every locale
     * (and x-default → the locale-negotiating root) so Google treats the five
     * translations as one page instead of five near-duplicates.
     *
     * @param  array<string, string>  $params
     */
    private static function localizedUrl(string $route, string $locale, array $params = []): Url
    {
        $url = Url::create(route($route, ['locale' => $locale, ...$params]));

        foreach (self::locales() as $alternate) {
            $url->addAlternate(route($route, ['locale' => $alternate, ...$params]), self::hreflang($alternate));
        }

        return $url->addAlternate(url('/'), 'x-default');
    }

    /** `pt_BR` → `pt-BR` (hreflang wants BCP 47). */
    public static function hreflang(string $locale): string
    {
        return str_replace('_', '-', $locale);
    }

    /** @return array<int, string> */
    public static function locales(): array
    {
        return (array) config('locale-cookie.supported', ['en']);
    }
}

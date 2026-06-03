<?php

namespace App\Console\Commands;

use App\Enums\ProjectCategory;
use App\Models\Project;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Write public/sitemap.xml (index) and public/sitemap-{pages,projects}.xml';

    public function handle(): int
    {
        $this->writePages();
        $this->writeProjects();
        $this->writeIndex();

        $this->info('Wrote sitemap.xml, sitemap-pages.xml, sitemap-projects.xml');

        return self::SUCCESS;
    }

    private function writePages(): void
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
        ];

        $sitemap = Sitemap::create();

        foreach ($pages as [$route, $frequency, $priority]) {
            $sitemap->add(
                Url::create(route($route))
                    ->setLastModificationDate($now)
                    ->setChangeFrequency($frequency)
                    ->setPriority($priority)
            );
        }

        $sitemap->writeToFile(public_path('sitemap-pages.xml'));
    }

    private function writeProjects(): void
    {
        $sitemap = Sitemap::create();

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

        $sitemap->writeToFile(public_path('sitemap-projects.xml'));
    }

    private function writeIndex(): void
    {
        $base = rtrim(config('app.url'), '/');

        SitemapIndex::create()
            ->add($base.'/sitemap-pages.xml')
            ->add($base.'/sitemap-projects.xml')
            ->writeToFile(public_path('sitemap.xml'));
    }
}

<?php

namespace App\Console\Commands;

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
        $sitemap = Sitemap::create()
            ->add(Url::create(route('home')))
            ->add(Url::create(route('about')))
            ->add(Url::create(route('projects.index')))
            ->add(Url::create(route('articles.index')))
            ->add(Url::create(route('links.index')))
            ->add(Url::create(route('open-source')))
            ->add(Url::create(route('stack')))
            ->add(Url::create(route('sponsors')));

        $sitemap->writeToFile(public_path('sitemap-pages.xml'));
    }

    private function writeProjects(): void
    {
        $sitemap = Sitemap::create();

        Project::query()->published()->orderBy('slug')->get(['slug', 'category', 'updated_at'])->each(
            fn (Project $project) => $sitemap->add(
                // Emit each project's canonical section URL (articles → /articles,
                // external links → /links, code → /projects) so the sitemap never
                // lists a link that just 301s elsewhere.
                Url::create(route($project->canonicalRouteName(), ['slug' => $project->slug]))
                    ->setLastModificationDate($project->updated_at ?? now())
            )
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

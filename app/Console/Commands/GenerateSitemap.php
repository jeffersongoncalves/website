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

    /**
     * SetLocale URL segment => hreflang attribute value.
     *
     * @var array<string, string>
     */
    private const HREFLANGS = ['pt' => 'pt-BR', 'en' => 'en', 'es' => 'es'];

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
        $sitemap = Sitemap::create();

        foreach (['home', 'about', 'projects.index', 'open-source', 'sponsors'] as $name) {
            $this->addAlternates($sitemap, $name);
        }

        $sitemap->writeToFile(public_path('sitemap-pages.xml'));
    }

    private function writeProjects(): void
    {
        $sitemap = Sitemap::create();

        Project::query()->published()->orderBy('slug')->pluck('slug')->each(
            fn (string $slug) => $this->addAlternates($sitemap, 'projects.show', ['slug' => $slug])
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

    /**
     * @param  array<string, string>  $params
     */
    private function addAlternates(Sitemap $sitemap, string $routeName, array $params = []): void
    {
        $alternates = [];

        foreach (array_keys(self::HREFLANGS) as $locale) {
            $alternates[$locale] = route($routeName, array_merge($params, ['locale' => $locale]));
        }

        foreach ($alternates as $loc) {
            $url = Url::create($loc);

            foreach ($alternates as $code => $href) {
                $url->addAlternate($href, self::HREFLANGS[$code]);
            }

            $url->addAlternate($alternates['en'], 'x-default');

            $sitemap->add($url);
        }
    }
}

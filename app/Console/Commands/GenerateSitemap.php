<?php

namespace App\Console\Commands;

use App\Models\Project;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Write the public/sitemap.xml file (replaces the dynamic /sitemap.xml route)';

    /**
     * SetLocale URL segment => hreflang attribute value.
     *
     * @var array<string, string>
     */
    private const HREFLANGS = ['pt' => 'pt-BR', 'en' => 'en', 'es' => 'es'];

    public function handle(): int
    {
        $sitemap = Sitemap::create();

        foreach (['home', 'about', 'projects.index', 'open-source', 'sponsors'] as $name) {
            $this->addAlternates($sitemap, $name);
        }

        Project::query()->published()->orderBy('slug')->pluck('slug')->each(
            fn (string $slug) => $this->addAlternates($sitemap, 'projects.show', ['slug' => $slug])
        );

        $path = public_path('sitemap.xml');
        $sitemap->writeToFile($path);

        $this->info("Wrote {$path}");

        return self::SUCCESS;
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

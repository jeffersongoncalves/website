<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Response;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapController
{
    /**
     * SetLocale URL segment => hreflang attribute value.
     *
     * @var array<string, string>
     */
    private const HREFLANGS = ['pt' => 'pt-BR', 'en' => 'en', 'es' => 'es'];

    public function __invoke(): Response
    {
        $sitemap = Sitemap::create();

        foreach (['home', 'about', 'projects.index', 'open-source', 'sponsors'] as $name) {
            $this->addAlternates($sitemap, $name);
        }

        Project::query()->published()->orderBy('slug')->pluck('slug')->each(
            fn (string $slug) => $this->addAlternates($sitemap, 'projects.show', ['slug' => $slug])
        );

        return response($sitemap->render(), 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Add one localized URL per supported locale, each carrying the full
     * `<xhtml:link rel="alternate">` set + x-default for `en`.
     *
     * @param  array<string, string>  $params
     */
    private function addAlternates(Sitemap $sitemap, string $routeName, array $params = []): void
    {
        $alternates = [];

        foreach (array_keys(self::HREFLANGS) as $locale) {
            $alternates[$locale] = route($routeName, array_merge($params, ['locale' => $locale]));
        }

        foreach ($alternates as $locale => $loc) {
            $url = Url::create($loc);

            foreach ($alternates as $code => $href) {
                $url->addAlternate($href, self::HREFLANGS[$code]);
            }

            $url->addAlternate($alternates['en'], 'x-default');

            $sitemap->add($url);
        }
    }
}

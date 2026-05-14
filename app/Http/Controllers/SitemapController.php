<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Models\Project;
use Illuminate\Http\Response;

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
        $entries = [];

        foreach (['home', 'about', 'projects.index', 'open-source', 'sponsors'] as $name) {
            $entries[] = $this->alternates($name, []);
        }

        foreach (Project::query()->published()->orderBy('slug')->pluck('slug') as $slug) {
            $entries[] = $this->alternates('projects.show', ['slug' => $slug]);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '
            .'xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";

        foreach ($entries as $alternates) {
            foreach ($alternates as $loc) {
                $xml .= "  <url>\n";
                $xml .= '    <loc>'.e($loc)."</loc>\n";
                foreach ($alternates as $code => $href) {
                    $xml .= '    <xhtml:link rel="alternate" hreflang="'.self::HREFLANGS[$code]
                        .'" href="'.e($href).'"/>'."\n";
                }
                $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="'.e($alternates['en']).'"/>'."\n";
                $xml .= "  </url>\n";
            }
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Build the locale => absolute URL map for one route.
     *
     * @param  array<string, string>  $params
     * @return array<string, string>
     */
    private function alternates(string $routeName, array $params): array
    {
        $alternates = [];

        foreach (array_keys(self::HREFLANGS) as $locale) {
            $alternates[$locale] = route($routeName, array_merge($params, ['locale' => $locale]));
        }

        return $alternates;
    }
}

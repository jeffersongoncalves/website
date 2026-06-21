<?php

declare(strict_types=1);

namespace App\Support;

use JeffersonGoncalves\NpmReadme\NpmReadme as Readme;

/**
 * App-side facade over jeffersongoncalves/laravel-npm-readme. The fetch + cache
 * + render lives in the package; the renderer (config `npm-readme.renderer`)
 * points at GithubReadme::renderMarkdown so npm READMEs render through
 * laravel-markdown (raw HTML kept) and are sanitised downstream in
 * ProjectShowPage, exactly like GitHub READMEs.
 */
class NpmReadme
{
    public static function fetchHtml(?string $npmUrl): ?string
    {
        return Readme::fetchHtml($npmUrl);
    }
}

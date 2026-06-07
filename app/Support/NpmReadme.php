<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Render the README of a registry npm package that has no GitHub repo. Unlike
 * GithubReadme (live conditional fetch + disk cache), the npm registry ships
 * the README markdown inline in the package document, so we just fetch that
 * document, render the markdown and cache the resulting HTML. Output is still
 * untrusted — ProjectShowPage sanitises it before display.
 */
class NpmReadme
{
    /** Minutes to cache rendered README HTML per package. */
    private const CACHE_MINUTES = 60;

    /** npm's sentinel when a package ships no README. */
    private const NO_README = 'ERROR: No README data found!';

    /**
     * Return the rendered README HTML for an npmjs.com package URL, or null
     * when the URL isn't an npm package, the registry has no document, or the
     * package ships no README.
     */
    public static function fetchHtml(?string $npmUrl): ?string
    {
        $package = self::packageFromUrl($npmUrl);

        if ($package === null) {
            return null;
        }

        return Cache::remember(
            "npm_readme:{$package}",
            now()->addMinutes(self::CACHE_MINUTES),
            fn () => self::renderPackageReadme($package)
        );
    }

    /**
     * Extract the package identifier (`name` or `@scope/name`) from an
     * npmjs.com/package URL. Returns null for anything else.
     */
    private static function packageFromUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (! preg_match('~npmjs\.com/package/(@[^/?#]+/[^/?#]+|[^/?#]+)~i', trim($url), $m)) {
            return null;
        }

        return rtrim($m[1], '/');
    }

    private static function renderPackageReadme(string $package): ?string
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'jeffersongoncalves-site',
                    'Accept' => 'application/json',
                ])
                ->get('https://registry.npmjs.org/'.$package);
        } catch (Throwable $e) {
            Log::warning('NpmReadme registry fetch failed', [
                'package' => $package,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $readme = $response->json('readme');

        if (! is_string($readme)) {
            return null;
        }

        $readme = trim($readme);

        if ($readme === '' || $readme === self::NO_README) {
            return null;
        }

        return Markdown::render($readme, headingPermalinks: true);
    }
}

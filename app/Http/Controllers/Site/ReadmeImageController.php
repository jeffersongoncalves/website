<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Support\ReadmeImageCache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a README image cached behind our own URL — see ReadmeImageCache's
 * docblock for why (GitHub's raw-content CDN measured a 54s LCP on a
 * hotlinked banner image in production). The route takes an arbitrary
 * encoded URL, so the host allow-list in ReadmeImageCache is re-checked
 * here rather than trusted just because GithubReadme is normally the only
 * thing that generates these URLs.
 */
class ReadmeImageController
{
    public function __invoke(string $encoded): Response
    {
        $url = ReadmeImageCache::decode($encoded);

        if ($url === null || ! ReadmeImageCache::isAllowedHost($url)) {
            abort(404);
        }

        ReadmeImageCache::warm($url);

        $response = ReadmeImageCache::response($url);

        if ($response === null) {
            abort(404);
        }

        $response->headers->set('Cache-Control', 'public, max-age='.ReadmeImageCache::TTL_SECONDS);

        return $response;
    }
}

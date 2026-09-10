<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Models\Project;
use App\Support\OgImageCache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves each project's social card behind our own URL — App\Support\OgImageCache
 * does the actual fetch-and-persist (also reused by WarmOgImageJob to pre-warm
 * without a request/response cycle).
 *
 * Both the real card and the fallback banner are served as a direct 200
 * response, never a redirect: WhatsApp/Facebook's link-preview crawler
 * frequently fails to follow a redirect on og:image, silently dropping the
 * preview image rather than following it to the real file.
 */
class OgImageController
{
    public function __invoke(string $slug): Response
    {
        $project = Project::query()->published()->where('slug', $slug)->first();

        if ($project === null || ! OgImageCache::hasSource($project)) {
            return $this->fallback();
        }

        OgImageCache::warm($project);

        $response = OgImageCache::response($slug);

        if ($response === null) {
            // Never fetched successfully (e.g. GitHub rate-limiting on the
            // very first visit) — nothing to serve from disk yet.
            return $this->fallback();
        }

        $response->headers->set('Cache-Control', 'public, max-age='.OgImageCache::TTL_SECONDS);

        return $response;
    }

    /**
     * The generic banner — served directly (never redirected) for the same
     * WhatsApp/Facebook-crawler reason as the real card above.
     */
    private function fallback(): Response
    {
        $body = file_get_contents(resource_path('images/github-og-en.png'));

        return response($body === false ? '' : $body, 200)
            ->header('Content-Type', 'image/png')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'public, max-age='.OgImageCache::TTL_SECONDS);
    }
}

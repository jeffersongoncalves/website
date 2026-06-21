<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Same-origin favicon proxy. The external-link cards used to point their
 * <img> straight at Google's S2 favicon service, so every visitor's browser
 * hit google.com once per distinct domain (a third-party connection + a
 * privacy leak of which links are shown). This fetches the icon server-side
 * once, caches the bytes, and serves them from our own host — the browser
 * never talks to Google.
 *
 * Not an SSRF vector: the fetch target host is always google.com; the visitor
 * only controls the `domain` query that Google itself resolves.
 */
class FaviconController
{
    private const TRANSPARENT_PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\nIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\r\n-\xb4\x00\x00\x00\x00IEND\xaeB`\x82";

    public function __invoke(Request $request): Response
    {
        $domain = strtolower((string) $request->query('domain', ''));

        // Reject anything that isn't a bare hostname before it reaches the
        // upstream URL (defence-in-depth even though the host is fixed).
        if ($domain === '' || filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            return $this->fallback();
        }

        $key = 'favicon:'.$domain;

        // Cache::remember treats a null value as a miss, so a failing domain
        // would re-hit google.com on every request. Store a `false` sentinel
        // for failures (short negative TTL) so they aren't re-fetched.
        $icon = Cache::get($key);

        if ($icon === null) {
            $fetched = $this->fetch($domain);
            Cache::put($key, $fetched ?? false, $fetched !== null ? now()->addDays(30) : now()->addHours(6));
            $icon = $fetched ?? false;
        }

        if (! is_array($icon)) {
            return $this->fallback();
        }

        return response($icon['body'], 200)
            ->header('Content-Type', $icon['type'])
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'public, max-age=2592000, immutable');
    }

    /**
     * @return array{body: string, type: string}|null
     */
    private function fetch(string $domain): ?array
    {
        try {
            $response = Http::timeout(6)
                ->get('https://www.google.com/s2/favicons', ['domain' => $domain, 'sz' => 64]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful() || $response->body() === '') {
            return null;
        }

        // Only cache + serve real images. Google S2 always returns image/*,
        // but validating it (like OgImageController) keeps a non-image response
        // from ever being served under an image content-type.
        $type = strtolower(trim((string) $response->header('Content-Type')));

        if (! str_starts_with($type, 'image/')) {
            return null;
        }

        return [
            'body' => $response->body(),
            'type' => $type,
        ];
    }

    private function fallback(): Response
    {
        // 1×1 transparent PNG — keeps the decorative <img> from showing a
        // broken-image glyph when the upstream icon is unavailable.
        return response(self::TRANSPARENT_PNG, 200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=86400');
    }
}

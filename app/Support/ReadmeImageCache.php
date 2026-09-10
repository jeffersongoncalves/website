<?php

declare(strict_types=1);

namespace App\Support;

use JeffersonGoncalves\ImageCache\ImageCache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fetch-and-persist cache for README images hotlinked from GitHub's own
 * asset hosts (raw.githubusercontent.com, camo/user-images/avatars...).
 * Real-user monitoring (Cloudflare Web Analytics) measured a Largest
 * Contentful Paint of 54 SECONDS on a public-apis/public-apis banner image
 * loaded directly from raw.githubusercontent.com — GitHub's raw-content CDN
 * is unreliable for hotlinked traffic. Proxying through our own cached copy
 * means only the first request after a cache miss pays that cost; every
 * later visitor gets our own fast, reliable copy.
 *
 * The actual fetch/persist/serve mechanics (SSRF-pinned download, redirect
 * re-validation, image/* gate, TTL-based disk caching) live in
 * jeffersongoncalves/laravel-image-cache — this class keeps the app-specific
 * bits: the host allow-list and the route's URL encode/decode.
 *
 * Only ALLOWED_HOSTS are ever fetched — this is reached via a public route
 * taking an arbitrary encoded URL, so the host allow-list is the actual
 * SSRF boundary, not a courtesy check. Enforced both when GithubReadme
 * rewrites README HTML (only these hosts get a proxy URL at all) and again
 * here on every fetch (the route's input isn't trusted just because we're
 * the ones who normally generate it).
 */
class ReadmeImageCache
{
    public const TTL_SECONDS = 86400;

    /** @var list<string> */
    public const ALLOWED_HOSTS = [
        'raw.githubusercontent.com',
        'camo.githubusercontent.com',
        'user-images.githubusercontent.com',
        'avatars.githubusercontent.com',
    ];

    private static function cache(): ImageCache
    {
        return new ImageCache('github', 'readme-images', self::TTL_SECONDS);
    }

    /** URL-safe base64 — this round-trips through a route segment. */
    public static function encode(string $url): string
    {
        return rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
    }

    public static function decode(string $encoded): ?string
    {
        $padded = str_pad($encoded, (int) (4 * ceil(strlen($encoded) / 4)), '=');
        $decoded = base64_decode(strtr($padded, '-_', '+/'), true);

        return $decoded !== false ? $decoded : null;
    }

    public static function isAllowedHost(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && in_array(strtolower($host), self::ALLOWED_HOSTS, true);
    }

    /**
     * Every allow-listed image src in rendered README HTML, deduped — used
     * by WarmReadmeCacheJob to pre-fetch images alongside the README itself
     * instead of leaving the first real visitor to pay each image's cold
     * fetch one at a time.
     *
     * @return list<string>
     */
    public static function extractAllowedImageUrls(string $html): array
    {
        if (preg_match_all('~<img\b[^>]*?\ssrc="([^"]+)"~i', $html, $m) === false) {
            return [];
        }

        $urls = array_map(
            static fn (string $src): string => html_entity_decode($src, ENT_QUOTES | ENT_HTML5),
            $m[1]
        );

        return array_values(array_unique(array_filter($urls, self::isAllowedHost(...))));
    }

    public static function path(string $url): string
    {
        return self::cache()->path(sha1($url));
    }

    /**
     * Fetch and persist the image if the disk copy is missing or older than
     * TTL_SECONDS. Leaves a stale disk copy in place rather than deleting it
     * when a refresh attempt fails — serving yesterday's copy beats erroring.
     */
    public static function warm(string $url): void
    {
        if (! self::isAllowedHost($url)) {
            return;
        }

        self::cache()->warm(sha1($url), $url);
    }

    /**
     * A ready-to-send response for a previously-warmed image, or null when
     * it hasn't been fetched successfully yet.
     */
    public static function response(string $url): ?Response
    {
        return self::cache()->response(sha1($url));
    }
}

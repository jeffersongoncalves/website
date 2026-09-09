<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use JeffersonGoncalves\SsrfGuard\SsrfGuard;
use Throwable;

/**
 * Fetch-and-persist cache for README images hotlinked from GitHub's own
 * asset hosts (raw.githubusercontent.com, camo/user-images/avatars...).
 * Real-user monitoring (Cloudflare Web Analytics) measured a Largest
 * Contentful Paint of 54 SECONDS on a public-apis/public-apis banner image
 * loaded directly from raw.githubusercontent.com — GitHub's raw-content CDN
 * is unreliable for hotlinked traffic. Proxying through our own cached copy
 * (same `github` disk + fetch/persist shape as OgImageCache) means only the
 * first request after a cache miss pays that cost; every later visitor gets
 * our own fast, reliable copy.
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

    public static function path(string $url): string
    {
        return 'readme-images/'.sha1($url);
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

        $disk = Storage::disk('github');
        $path = self::path($url);

        if ($disk->exists($path)
            && $disk->lastModified($path) >= now()->subSeconds(self::TTL_SECONDS)->timestamp) {
            return;
        }

        $fetched = self::fetch($url);

        if ($fetched !== null) {
            $disk->put($path, $fetched['body']);
            $disk->put($path.'.type', $fetched['type']);
        }
    }

    /**
     * @return array{body: string, type: string}|null
     */
    private static function fetch(string $url): ?array
    {
        $resolve = app(SsrfGuard::class)->resolveEntries($url);

        if ($resolve === null) {
            Log::warning('ReadmeImageCache refused a non-public host', ['url' => $url]);

            return null;
        }

        try {
            $response = Http::timeout(8)->withOptions([
                'curl' => [CURLOPT_RESOLVE => $resolve],
                'allow_redirects' => [
                    'max' => 3,
                    'strict' => true,
                    'referer' => false,
                    'protocols' => ['http', 'https'],
                    'on_redirect' => function ($request, $response, $uri): void {
                        if (app(SsrfGuard::class)->resolveEntries((string) $uri) === null) {
                            throw new \RuntimeException('Readme image redirect to non-public host blocked: '.$uri);
                        }
                    },
                ],
            ])->get($url);
        } catch (Throwable $e) {
            Log::warning('ReadmeImageCache fetch threw', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('ReadmeImageCache fetch failed', ['url' => $url, 'status' => $response->status()]);

            return null;
        }

        $type = strtolower(trim((string) $response->header('Content-Type')));

        if (! str_starts_with($type, 'image/')) {
            Log::warning('ReadmeImageCache rejected non-image upstream', ['url' => $url, 'type' => $type]);

            return null;
        }

        return [
            'body' => $response->body(),
            'type' => $type,
        ];
    }
}

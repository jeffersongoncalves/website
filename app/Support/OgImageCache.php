<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use JeffersonGoncalves\SsrfGuard\SsrfGuard;
use Throwable;

/**
 * Fetch-and-persist half of the social card behind /og/{slug}.png — kept
 * separate from OgImageController so WarmOgImageJob can pre-fetch a card
 * without going through an HTTP request/response cycle, the same split as
 * App\Support\GithubReadme (fetch+cache) vs the page that renders it.
 *
 * Persists to the `github` disk (reused from laravel-github-readme) rather
 * than the app cache store — these are ~100KB binary blobs, not the small
 * scalar values the cache store (currently the `database` driver) is meant
 * for. A `.type` sidecar file carries the real upstream Content-Type
 * instead of sniffing the bytes on read, which can misdetect an
 * unusual-but-valid image variant (and, in tests, fake non-image bytes).
 */
class OgImageCache
{
    public const TTL_SECONDS = 86400;

    public static function path(string $slug): string
    {
        return 'og-images/'.$slug;
    }

    /**
     * Whether this project has a real image source at all (a GitHub repo, or
     * a social_image that resolves to a public host) — distinct from
     * whether that source has been fetched successfully yet.
     */
    public static function hasSource(Project $project): bool
    {
        return self::sourceUrl($project) !== null;
    }

    /**
     * Fetch and persist the card if the disk copy is missing or older than
     * TTL_SECONDS. No-ops (cheaply) when already fresh, and leaves a stale
     * disk copy in place rather than deleting it when a refresh attempt
     * fails (e.g. GitHub rate-limiting) — serving yesterday's card beats
     * falling back to the generic banner.
     */
    public static function warm(Project $project): void
    {
        $source = self::sourceUrl($project);

        if ($source === null) {
            return;
        }

        $disk = Storage::disk('github');
        $path = self::path($project->slug);

        if ($disk->exists($path)
            && $disk->lastModified($path) >= now()->subSeconds(self::TTL_SECONDS)->timestamp) {
            return;
        }

        $fetched = self::fetch($source['url'], $source['resolve']);

        if ($fetched !== null) {
            $disk->put($path, $fetched['body']);
            $disk->put($path.'.type', $fetched['type']);
        }
    }

    /**
     * The image source plus, for untrusted hosts, the curl resolve map that
     * pins the connection to the IP we validated — see SsrfGuard::resolveEntries().
     *
     * @return array{url: string, resolve: list<string>|null}|null
     */
    private static function sourceUrl(Project $project): ?array
    {
        if ($project->github_url && preg_match('~github\.com/([^/?#]+/[^/?#]+)~i', $project->github_url, $m)) {
            // Trusted first-party host — no pinning needed.
            return ['url' => 'https://opengraph.githubassets.com/1/'.rtrim($m[1], '/'), 'resolve' => null];
        }

        // social_image is scraped from arbitrary external sites at import time,
        // so it is untrusted. Only proxy it when it resolves to a public host —
        // otherwise /og/{slug}.png becomes an SSRF window into internal services.
        if (! empty($project->social_image)) {
            $resolve = app(SsrfGuard::class)->resolveEntries((string) $project->social_image);

            if ($resolve !== null) {
                return ['url' => (string) $project->social_image, 'resolve' => $resolve];
            }
        }

        return null;
    }

    /**
     * @param  list<string>|null  $resolve  curl CURLOPT_RESOLVE entries (host:port:ip)
     * @return array{body: string, type: string}|null
     */
    private static function fetch(string $source, ?array $resolve = null): ?array
    {
        try {
            $options = [
                // Follow redirects but re-validate every hop: the CURLOPT_RESOLVE
                // pin only covers the first host, so without this a public host
                // could 302 to 169.254.169.254/localhost and defeat the IP guard.
                'allow_redirects' => [
                    'max' => 3,
                    'strict' => true,
                    'referer' => false,
                    'protocols' => ['http', 'https'],
                    'on_redirect' => function ($request, $response, $uri): void {
                        if (app(SsrfGuard::class)->resolveEntries((string) $uri) === null) {
                            throw new \RuntimeException('OG fetch redirect to non-public host blocked: '.$uri);
                        }
                    },
                ],
            ];

            if ($resolve !== null) {
                $options['curl'] = [CURLOPT_RESOLVE => $resolve];
            }

            $response = Http::timeout(8)->withOptions($options)->get($source);
        } catch (Throwable $e) {
            Log::warning('OgImageCache fetch threw', ['source' => $source, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('OgImageCache fetch failed', ['source' => $source, 'status' => $response->status()]);

            return null;
        }

        // This is proxied verbatim to visitors. Untrusted upstreams
        // (social_image) could return text/html+script; serving that from
        // our own origin would be stored XSS, so reject non-images.
        $type = strtolower(trim((string) $response->header('Content-Type')));

        if (! str_starts_with($type, 'image/')) {
            Log::warning('OgImageCache rejected non-image upstream', ['source' => $source, 'type' => $type]);

            return null;
        }

        return [
            'body' => $response->body(),
            'type' => $type,
        ];
    }
}

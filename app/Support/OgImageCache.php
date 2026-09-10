<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Project;
use JeffersonGoncalves\ImageCache\ImageCache;
use JeffersonGoncalves\SsrfGuard\SsrfGuard;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fetch-and-persist half of the social card behind /og/{slug}.png — kept
 * separate from OgImageController so WarmOgImageJob can pre-fetch a card
 * without going through an HTTP request/response cycle, the same split as
 * App\Support\GithubReadme (fetch+cache) vs the page that renders it.
 *
 * The actual fetch/persist/serve mechanics (SSRF-pinned download, redirect
 * re-validation, image/* gate, TTL-based disk caching with a `.type`
 * sidecar) live in jeffersongoncalves/laravel-image-cache — this class only
 * keeps the app-specific bit: resolving *what* URL is the source for a
 * given project.
 */
class OgImageCache
{
    public const TTL_SECONDS = 86400;

    private static function cache(): ImageCache
    {
        return new ImageCache('github', 'og-images', self::TTL_SECONDS);
    }

    public static function path(string $slug): string
    {
        return self::cache()->path($slug);
    }

    /**
     * A ready-to-send response for a project's previously-warmed card, or
     * null when it hasn't been fetched successfully yet.
     */
    public static function response(string $slug): ?Response
    {
        return self::cache()->response($slug);
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

        self::cache()->warm($project->slug, $source['url'], $source['resolve']);
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
}

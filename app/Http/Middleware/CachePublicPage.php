<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Full-page response cache for the stateless public site. Caches 200 GET
 * responses keyed on locale + full URL, with a version token that
 * ProjectObserver bumps on any Project change so edits surface immediately.
 * Skips authenticated requests and anything that sets a session/flash.
 */
class CachePublicPage
{
    private const VERSION_KEY = 'pages:version';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldCache($request)) {
            return $next($request);
        }

        $key = $this->cacheKey($request);

        /** @var array{content: string, type: string}|null $cached */
        $cached = Cache::get($key);

        if ($cached !== null) {
            return response($cached['content'], 200)
                ->header('Content-Type', $cached['type'])
                ->header('X-Page-Cache', 'HIT');
        }

        $response = $next($request);

        if ($response->getStatusCode() === 200 && $response->getContent() !== false) {
            Cache::put($key, [
                'content' => $response->getContent(),
                'type' => (string) $response->headers->get('Content-Type', 'text/html; charset=UTF-8'),
            ], (int) config('filakit.page_cache_ttl', 3600));

            $response->headers->set('X-Page-Cache', 'MISS');
        }

        return $response;
    }

    /** Bump the version token to invalidate every cached page. */
    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    private function shouldCache(Request $request): bool
    {
        return config('filakit.page_cache_enabled', true)
            && $request->isMethod('GET')
            && $request->user() === null;
    }

    private function cacheKey(Request $request): string
    {
        // Fold the deployed app version into the key so a new release
        // invalidates every cached page automatically — otherwise stale HTML
        // (e.g. the old version in the footer) survives the deploy until TTL.
        $build = (string) config('app.version', '');

        return 'page:'.$build.':'.self::version().':'.app()->getLocale().':'.sha1($request->fullUrl());
    }

    private static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}

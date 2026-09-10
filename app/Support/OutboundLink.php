<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use JeffersonGoncalves\LaravelShortUrl\Facades\ShortUrl;
use JeffersonGoncalves\LaravelShortUrl\Models\ShortUrl as ShortUrlModel;
use Throwable;

/**
 * Routes an outbound (off-site) href through a short URL so the click is
 * counted by jeffersongoncalves/laravel-short-url.
 *
 * One short URL per destination, deduped on `destination_url` — the same
 * repo linked from a card, a detail page and the footer shares one row, so
 * the metrics read "clicks on this destination", not "clicks on this
 * placement". Rows are created lazily on first render and memoised in the
 * cache, keyed by the destination, so steady state is a cache hit.
 *
 * Never breaks a page: any failure (DB down, plan limit, invalid URL) falls
 * back to the raw destination, so the link still works untracked.
 */
final class OutboundLink
{
    /** internal_ref stamped on every row created here, so the admin can tell
     *  site-generated links apart from hand-created ones. */
    public const REF = 'outbound';

    public static function to(?string $url, ?string $title = null): ?string
    {
        if ($url === null || $url === '' || ! self::isExternal($url)) {
            return $url;
        }

        try {
            $key = Cache::rememberForever(
                'outbound-link:'.sha1($url),
                fn (): string => self::keyFor($url, $title),
            );
        } catch (Throwable) {
            return $url;
        }

        return url('/'.$key);
    }

    /**
     * Batch counterpart to to() — resolves every url in one pass instead of
     * each paying its own cache-read + DB-lookup round trip sequentially.
     * A README with hundreds/thousands of links (a big awesome-list) turned
     * the naive per-link path into a multi-minute render (confirmed: a 504
     * gateway timeout on awesome-selfhosted/awesome-selfhosted). Delegates
     * the actual batching (cache + whereIn + create-only-what's-missing) to
     * jeffersongoncalves/laravel-short-url's own ShortUrl::resolveMany() —
     * this method only carries the app-specific bits: filtering out
     * non-external urls up front, and stamping REF on rows resolveMany()
     * mints so the admin can tell site-generated links apart from
     * hand-created ones (same tagging to() already does for the single-url
     * path).
     *
     * @param  list<string>  $urls
     * @return array<string, string> original url => resolved url (a short
     *                               url for an external destination, or
     *                               the original url unchanged otherwise —
     *                               every input url is present in the
     *                               result, so callers never need a
     *                               separate null-check per lookup)
     */
    public static function resolveMany(array $urls): array
    {
        $result = [];
        $external = [];

        foreach (array_unique($urls) as $url) {
            if (self::isExternal($url)) {
                $external[] = $url;
            } else {
                $result[$url] = $url;
            }
        }

        if ($external === []) {
            return $result;
        }

        try {
            $resolved = ShortUrl::resolveMany($external, ['internal_ref' => self::REF]);
        } catch (Throwable) {
            return $result + array_combine($external, $external);
        }

        return $result + $resolved;
    }

    /**
     * Off-site means an http(s) URL whose host is not this app's own — a
     * relative path, a mailto:, or a link back to the site is left alone.
     */
    private static function isExternal(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        if (! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return false;
        }

        return strcasecmp($host, (string) parse_url((string) config('app.url'), PHP_URL_HOST)) !== 0;
    }

    /**
     * Resolve-or-create, under a lock so two concurrent first-renders of the
     * same destination can't each mint a key for it.
     */
    private static function keyFor(string $url, ?string $title): string
    {
        return Cache::lock('outbound-link-lock:'.sha1($url), 10)->block(5, function () use ($url, $title): string {
            $existing = ShortUrlModel::query()
                ->where('destination_url', $url)
                ->value('url_key');

            if (is_string($existing)) {
                return $existing;
            }

            return ShortUrl::create([
                'destination_url' => $url,
                'title' => $title ?? (string) parse_url($url, PHP_URL_HOST),
                'internal_ref' => self::REF,
            ])->url_key;
        });
    }
}

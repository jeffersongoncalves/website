<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JeffersonGoncalves\LaravelShortUrl\Models\CustomDomain;
use JeffersonGoncalves\LaravelShortUrl\Models\ShortUrl;
use JeffersonGoncalves\LaravelShortUrl\Pipeline\Stages\ResolveShortUrl;
use JeffersonGoncalves\LaravelShortUrl\ShortUrlManager;
use Throwable;

/**
 * Flips one chunk of short urls from 302 to 301 (rows with an explicit
 * 307/308 are left alone) and flushes their resolve cache.
 *
 * The mass update skips ShortUrlObserver, so the cache is flushed here per
 * row — otherwise redirects keep serving the cached 302 until the TTL. The
 * resolve cache is keyed by request host: the app host plus every custom
 * domain (s.*).
 *
 * Chunked because doing all ~145k rows inside the operation job blew the
 * `default` supervisor's 60s timeout in production.
 */
class SwitchShortUrlsToPermanentRedirectChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  list<int>  $ids
     */
    public function __construct(public array $ids) {}

    /**
     * Dispatches one job per 500 rows still on 302. Returns the row count.
     */
    public static function dispatchAll(): int
    {
        $dispatched = 0;

        ShortUrl::withTrashed()
            ->where('redirect_status_code', 302)
            ->select('id')
            ->chunkById(500, function ($rows) use (&$dispatched): void {
                self::dispatch(array_values(array_map(intval(...), $rows->pluck('id')->all())));
                $dispatched += $rows->count();
            });

        return $dispatched;
    }

    public function handle(): void
    {
        $hosts = CustomDomain::query()->pluck('domain')
            ->push(config('short-url.route.domain') ?? parse_url((string) config('app.url'), PHP_URL_HOST) ?? 'localhost')
            ->unique();

        $rows = ShortUrl::withTrashed()
            ->whereKey($this->ids)
            ->where('redirect_status_code', 302)
            ->get(['id', 'url_key', 'destination_url']);

        ShortUrl::withTrashed()
            ->whereKey($rows->modelKeys())
            ->update(['redirect_status_code' => 301]);

        foreach ($rows as $row) {
            foreach ($hosts as $host) {
                Cache::forget(ResolveShortUrl::cacheKey($host, $row->url_key));
            }

            Cache::forget(ShortUrlManager::destinationCacheKey($row->destination_url));
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('SwitchShortUrlsToPermanentRedirectChunkJob failed', [
            'chunk_size' => count($this->ids),
            'error' => $e?->getMessage(),
        ]);
    }
}

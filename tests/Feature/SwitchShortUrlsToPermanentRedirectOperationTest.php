<?php

declare(strict_types=1);

use App\Jobs\SwitchShortUrlsToPermanentRedirectChunkJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use JeffersonGoncalves\LaravelShortUrl\Contracts\SettingsRepository;
use JeffersonGoncalves\LaravelShortUrl\Models\ShortUrl;
use JeffersonGoncalves\LaravelShortUrl\Pipeline\Stages\ResolveShortUrl;

it('dispatches one chunk job per batch of 302 short urls and stores the 301 setting', function (): void {
    $temporary = ShortUrl::factory()->create(['redirect_status_code' => 302]);
    ShortUrl::factory()->create(['redirect_status_code' => 307]);

    (require base_path('operations/2026_09_23_220000_switch_short_urls_to_permanent_redirect.php'))->process();

    Queue::assertPushed(
        SwitchShortUrlsToPermanentRedirectChunkJob::class,
        fn (SwitchShortUrlsToPermanentRedirectChunkJob $job): bool => $job->ids === [$temporary->id],
    );
    expect(app(SettingsRepository::class)->get('redirect.default_status_code'))->toBe(301);
});

it('flips a chunk from 302 to 301, keeps explicit codes, and flushes the resolve cache', function (): void {
    $temporary = ShortUrl::factory()->create(['redirect_status_code' => 302]);
    $explicit = ShortUrl::factory()->create(['redirect_status_code' => 307]);

    $host = parse_url((string) config('app.url'), PHP_URL_HOST);
    Cache::put(ResolveShortUrl::cacheKey($host, $temporary->url_key), 'stale');

    (new SwitchShortUrlsToPermanentRedirectChunkJob([$temporary->id, $explicit->id]))->handle();

    expect($temporary->fresh()->redirect_status_code)->toBe(301)
        ->and($explicit->fresh()->redirect_status_code)->toBe(307)
        ->and(Cache::has(ResolveShortUrl::cacheKey($host, $temporary->url_key)))->toBeFalse();
});

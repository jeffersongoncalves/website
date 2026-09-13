<?php

declare(strict_types=1);

use App\Support\OutboundLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use JeffersonGoncalves\LaravelShortUrl\Jobs\TrackShortUrlVisitJob;
use JeffersonGoncalves\LaravelShortUrl\Models\ShortUrl;
use JeffersonGoncalves\LaravelShortUrl\Models\Visit;

uses(RefreshDatabase::class);

it('mints one short URL per off-site destination and reuses it', function () {
    $first = OutboundLink::to('https://github.com/jeffersongoncalves/filament-short-url', 'filament-short-url');
    $second = OutboundLink::to('https://github.com/jeffersongoncalves/filament-short-url', 'somewhere else');

    expect($second)->toBe($first)
        ->and(ShortUrl::query()->count())->toBe(1);

    // Cold cache (a deploy ran cache:clear) must find the existing row, not
    // mint a second key for the same destination.
    Cache::flush();

    expect(OutboundLink::to('https://github.com/jeffersongoncalves/filament-short-url'))->toBe($first)
        ->and(ShortUrl::query()->count())->toBe(1);

    $row = ShortUrl::query()->firstOrFail();

    expect($row->internal_ref)->toBe(OutboundLink::REF)
        ->and($row->title)->toBe('filament-short-url')
        ->and($first)->toBe(url('/'.$row->url_key));
});

it('leaves links that are not off-site http(s) alone', function (?string $url) {
    expect(OutboundLink::to($url))->toBe($url)
        ->and(ShortUrl::query()->count())->toBe(0);
})->with([
    'own host' => [fn () => config('app.url').'/projects'],
    'relative' => ['/projects'],
    'mailto' => ['mailto:contato@jeffersongoncalves.dev.br'],
    'empty' => [''],
    'null' => [null],
    'non-http(s) scheme with a real external host' => ['ftp://example.com/file'],
]);

it('resolveMany short-circuits without a short-url lookup when every url is internal', function () {
    $urls = [config('app.url').'/projects', '/relative', 'mailto:hi@example.com'];

    expect(OutboundLink::resolveMany($urls))->toBe([
        config('app.url').'/projects' => config('app.url').'/projects',
        '/relative' => '/relative',
        'mailto:hi@example.com' => 'mailto:hi@example.com',
    ]);
    expect(ShortUrl::query()->count())->toBe(0);
});

it('redirects the minted short URL to its destination and queues the click', function () {
    $destination = 'https://packagist.org/packages/jeffersongoncalves/filakitv5';

    $this->get(OutboundLink::to($destination))
        ->assertRedirect($destination);

    Queue::assertPushed(TrackShortUrlVisitJob::class);
});

it('records the Cloudflare country on the visit even though the job runs off-request', function () {
    $destination = 'https://github.com/jeffersongoncalves/laravel-short-url';

    $this->withHeaders(['CF-IPCountry' => 'BR', 'CF-IPCountry-Name' => 'Brazil'])
        ->get(OutboundLink::to($destination))
        ->assertRedirect($destination);

    $job = Queue::pushed(TrackShortUrlVisitJob::class)->firstOrFail();

    // Stand in for the worker: the visitor's request is gone by the time the
    // job runs, so the geo has to have travelled in the payload.
    app()->instance('request', Request::create('/', 'GET'));
    app()->call([$job, 'handle']);

    $visit = Visit::query()->firstOrFail();

    expect($visit->country_code)->toBe('BR')
        ->and($visit->country)->toBe('Brazil');
});

it('falls back to the raw destination when the short URL cannot be minted', function () {
    // Tracking must never take an outbound link — or the page around it —
    // down: a broken short-url side drops back to the plain destination.
    Schema::drop(ShortUrl::make()->getTable());

    expect(OutboundLink::to('https://example.com/docs'))->toBe('https://example.com/docs');
});

it('resolves a batch of urls in one pass, deduping repeats and mapping every input', function () {
    $urls = [
        'https://github.com/jeffersongoncalves/filakitv5',
        'https://github.com/jeffersongoncalves/filament-ban',
        'https://github.com/jeffersongoncalves/filakitv5', // repeated
        config('app.url').'/projects', // own host — passes through unresolved
        '/relative', // not off-site at all
    ];

    $resolved = OutboundLink::resolveMany($urls);

    expect($resolved)->toHaveCount(4) // dedup collapses the repeat
        ->and($resolved['https://github.com/jeffersongoncalves/filakitv5'])
        ->toBe(OutboundLink::to('https://github.com/jeffersongoncalves/filakitv5'))
        ->and($resolved[config('app.url').'/projects'])->toBe(config('app.url').'/projects')
        ->and($resolved['/relative'])->toBe('/relative')
        ->and(ShortUrl::query()->count())->toBe(2); // filakitv5 + filament-ban only
});

it('reuses cached and already-persisted short urls instead of re-minting them', function () {
    $destination = 'https://github.com/jeffersongoncalves/filament-logo';
    $first = OutboundLink::to($destination);

    // Cold cache — same shape as the single-url path's own reuse test —
    // must still resolve from the DB row, not mint a second one.
    Cache::flush();

    expect(OutboundLink::resolveMany([$destination]))->toBe([$destination => $first])
        ->and(ShortUrl::query()->count())->toBe(1);
});

it('falls back to the raw destination per-url when minting fails, without losing the rest', function () {
    Schema::drop(ShortUrl::make()->getTable());

    $resolved = OutboundLink::resolveMany([
        'https://example.com/a',
        'https://example.com/b',
    ]);

    expect($resolved)->toBe([
        'https://example.com/a' => 'https://example.com/a',
        'https://example.com/b' => 'https://example.com/b',
    ]);
});

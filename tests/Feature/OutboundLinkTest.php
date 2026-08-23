<?php

declare(strict_types=1);

use App\Support\OutboundLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use JeffersonGoncalves\LaravelShortUrl\Jobs\TrackShortUrlVisitJob;
use JeffersonGoncalves\LaravelShortUrl\Models\ShortUrl;

uses(RefreshDatabase::class);

it('mints one short URL per off-site destination and reuses it', function () {
    $first = OutboundLink::to('https://github.com/jeffersongoncalves/filament-short-url', 'filament-short-url');
    $second = OutboundLink::to('https://github.com/jeffersongoncalves/filament-short-url', 'somewhere else');

    expect($second)->toBe($first)
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
]);

it('redirects the minted short URL to its destination and queues the click', function () {
    $destination = 'https://packagist.org/packages/jeffersongoncalves/filakitv5';

    $this->get(OutboundLink::to($destination))
        ->assertRedirect($destination);

    Queue::assertPushed(TrackShortUrlVisitJob::class);
});

it('falls back to the raw destination when the short URL cannot be minted', function () {
    // Tracking must never take an outbound link — or the page around it —
    // down: a broken short-url side drops back to the plain destination.
    Schema::drop(ShortUrl::make()->getTable());

    expect(OutboundLink::to('https://example.com/docs'))->toBe('https://example.com/docs');
});

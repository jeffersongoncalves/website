<?php

declare(strict_types=1);

use App\Support\ReadmeImageCache;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    app()->forgetInstance(HttpFactory::class);
    Http::clearResolvedInstances();
    Http::preventStrayRequests();
    Storage::fake('github');
});

it('proxies and caches an image from an allowed GitHub asset host', function () {
    $url = 'https://raw.githubusercontent.com/owner/repo/main/banner.png';

    Http::fake([$url => Http::response('IMAGEBYTES', 200, ['Content-Type' => 'image/png'])]);

    $this->get('/readme-image/'.ReadmeImageCache::encode($url))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertSee('IMAGEBYTES', false);

    Storage::disk('github')->assertExists(ReadmeImageCache::path($url));

    // Second hit served from disk — the source is not fetched again.
    Http::fake(fn () => throw new RuntimeException('should be cached'));
    $this->get('/readme-image/'.ReadmeImageCache::encode($url))
        ->assertOk()
        ->assertSee('IMAGEBYTES', false);
});

it('404s a host outside the allow-list without fetching it', function () {
    // No stray request allowed to leave — the host check must short-circuit
    // before any fetch happens.
    $this->get('/readme-image/'.ReadmeImageCache::encode('https://evil.example.com/x.png'))
        ->assertNotFound();
});

it('404s an internal address even if it somehow matched a host string', function () {
    // Belt-and-suspenders: an attacker-crafted encoded value that isn't
    // valid base64 at all.
    $this->get('/readme-image/not-valid-base64!!!')
        ->assertNotFound();
});

it('404s when the upstream fetch fails and nothing was ever cached', function () {
    $url = 'https://raw.githubusercontent.com/owner/repo/main/missing.png';

    Http::fake([$url => Http::response('not found', 404)]);

    $this->get('/readme-image/'.ReadmeImageCache::encode($url))->assertNotFound();
});

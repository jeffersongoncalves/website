<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

it('proxies and serves a favicon for a valid domain', function () {
    Http::fake(['www.google.com/s2/*' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png'])]);

    $this->get(route('favicon-proxy', ['domain' => 'example.com']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('serves a transparent fallback for an invalid domain', function () {
    $this->get(route('favicon-proxy', ['domain' => 'not a domain!!']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('serves a fallback when the upstream icon is unavailable', function () {
    Http::fake(['www.google.com/s2/*' => Http::response('', 404)]);

    $this->get(route('favicon-proxy', ['domain' => 'broken.test']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('rejects a non-image upstream content-type and serves the fallback', function () {
    // A compromised/misbehaving upstream returning text/html must never be
    // served back under an image content-type (stored-XSS guard).
    Http::fake(['www.google.com/s2/*' => Http::response('<script>alert(1)</script>', 200, ['Content-Type' => 'text/html'])]);

    $response = $this->get(route('favicon-proxy', ['domain' => 'evil.test']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect($response->getContent())->not->toContain('<script>');
});

it('sends nosniff on a proxied favicon', function () {
    Http::fake(['www.google.com/s2/*' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png'])]);

    $this->get(route('favicon-proxy', ['domain' => 'example.com']))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

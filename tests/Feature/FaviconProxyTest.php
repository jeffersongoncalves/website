<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

it('proxies and serves a favicon for a valid domain', function () {
    Http::fake(['www.google.com/s2/*' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png'])]);

    $this->get(route('favicon.proxy', ['domain' => 'example.com']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('serves a transparent fallback for an invalid domain', function () {
    $this->get(route('favicon.proxy', ['domain' => 'not a domain!!']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('serves a fallback when the upstream icon is unavailable', function () {
    Http::fake(['www.google.com/s2/*' => Http::response('', 404)]);

    $this->get(route('favicon.proxy', ['domain' => 'broken.test']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

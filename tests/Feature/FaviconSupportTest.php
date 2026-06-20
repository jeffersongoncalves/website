<?php

use App\Support\FaviconSupport;

// FaviconSupport::routes() is registered from bootstrap/app.php's withRouting
// `then` callback, gated on config('filakit.favicon.enabled'). These tests
// exercise the route handlers + the appleHeadLinks() helper, which are the
// branches left uncovered once /manifest.json is tested elsewhere.

it('serves browserconfig.xml with the xml content-type and tile logos', function () {
    $response = $this->get('/browserconfig.xml');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml');

    $body = $response->getContent();

    expect($body)
        ->toContain('<browserconfig>')
        ->toContain('<square70x70logo')
        ->toContain('<square150x150logo')
        ->toContain('<square310x310logo')
        ->toContain('<TileColor>#ffffff</TileColor>');
});

it('serves the favicon.ico with the x-icon content-type and a non-empty body', function () {
    $response = $this->get('/favicon.ico');

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/x-icon');

    expect($response->getContent())->not->toBe('');
});

it('builds the apple touch icon head links for every iOS size', function () {
    $links = FaviconSupport::appleHeadLinks();

    $sizes = ['57x57', '60x60', '72x72', '76x76', '114x114', '120x120', '144x144', '152x152', '180x180'];

    expect($links)->toHaveCount(count($sizes));

    foreach ($links as $i => $link) {
        expect($link['rel'])->toBe('apple-touch-icon');
        expect($link['sizes'])->toBe($sizes[$i]);
        // href is resolved through the committed Vite manifest (hashed path).
        expect($link['href'])
            ->toContain('/build/')
            ->toEndWith('.png');
    }
});

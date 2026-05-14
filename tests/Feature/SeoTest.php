<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders per-page SEO meta on a site page', function () {
    $response = $this->get('/en');

    $response->assertOk();
    $response->assertSee('<meta name="description"', false);
    $response->assertSee('<link rel="canonical"', false);
    $response->assertSee('hreflang="pt-BR"', false);
    $response->assertSee('hreflang="x-default"', false);
    $response->assertSee('property="og:title"', false);
    $response->assertSee('name="twitter:card"', false);
    $response->assertSee('application/ld+json', false);
    $response->assertSee('"@type":"Person"', false);
    $response->assertSee('"@type":"WebSite"', false);
});

it('serves a valid XML sitemap', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml');
    $response->assertSee('<urlset', false);
    $response->assertSee('hreflang="x-default"', false);
    $response->assertSee('/en', false);
});

// public/robots.txt is a static file served by the web server, not a route —
// it can't be asserted through the framework HTTP test harness.

<?php

declare(strict_types=1);

it('renders per-page SEO meta on a site page', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('<meta name="description"', false);
    $response->assertSee('<link rel="canonical"', false);
    $response->assertSee('property="og:title"', false);
    $response->assertSee('name="twitter:card"', false);
    // AppServiceProvider::configureSeo()'s SEODataTransformer fills in a
    // locale-aware banner whenever a page (like the home page) sets no image.
    $response->assertSee('property="og:image"', false);
});

it('generates a valid XML sitemap', function () {
    $this->artisan('sitemap:generate')->assertSuccessful();

    $xml = $this->get(route('sitemap'))->assertOk()->getContent();

    expect($xml)->toContain('<urlset')
        ->not->toContain('<sitemapindex');
});

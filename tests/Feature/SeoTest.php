<?php

declare(strict_types=1);

it('renders per-page SEO meta on a site page', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('<meta name="description"', false);
    $response->assertSee('<link rel="canonical"', false);
    $response->assertSee('property="og:title"', false);
    $response->assertSee('name="twitter:card"', false);
});

it('generates a valid XML sitemap', function () {
    $xml = $this->get(route('sitemap'))->assertOk()->getContent();

    expect($xml)->toContain('<urlset')
        ->not->toContain('<sitemapindex');
});

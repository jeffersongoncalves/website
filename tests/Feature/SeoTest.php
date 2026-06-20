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
    foreach (['sitemap.xml', 'sitemap-pages.xml', 'sitemap-projects.xml'] as $f) {
        @unlink(public_path($f));
    }

    $this->artisan('sitemap:generate')->assertSuccessful();

    expect(file_exists(public_path('sitemap.xml')))->toBeTrue();

    // Single inline urlset (no longer a sitemapindex pointing at split files).
    $xml = (string) file_get_contents(public_path('sitemap.xml'));
    expect($xml)->toContain('<urlset')
        ->not->toContain('<sitemapindex');
    expect(file_exists(public_path('sitemap-pages.xml')))->toBeFalse();
});

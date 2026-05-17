<?php

it('renders per-page SEO meta on a site page', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('<meta name="description"', false);
    $response->assertSee('<link rel="canonical"', false);
    $response->assertSee('property="og:title"', false);
    $response->assertSee('name="twitter:card"', false);
});

it('generates a valid XML sitemap', function () {
    $files = ['sitemap.xml', 'sitemap-pages.xml', 'sitemap-projects.xml'];
    foreach ($files as $f) {
        @unlink(public_path($f));
    }

    $this->artisan('sitemap:generate')->assertSuccessful();

    expect(file_exists(public_path('sitemap.xml')))->toBeTrue();
    expect(file_get_contents(public_path('sitemap.xml')))->toContain('<sitemapindex');
    expect(file_get_contents(public_path('sitemap-pages.xml')))->toContain('<urlset');
});

<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;

it('renders per-page SEO meta on a site page', function () {
    $response = $this->get('/pt_BR');

    $response->assertOk();
    $response->assertSee('<meta name="description"', false);
    $response->assertSee('<link rel="canonical"', false);
    $response->assertSee('property="og:title"', false);
    $response->assertSee('name="twitter:card"', false);
    // AppServiceProvider::configureSeo()'s SEODataTransformer fills in a
    // locale-aware banner whenever a page (like the home page) sets no image.
    $response->assertSee('property="og:image"', false);
});

it('renders hreflang alternates for every locale on a site page', function () {
    $response = $this->get('/pt_BR/about')->assertOk();

    foreach (['pt-BR' => 'pt_BR', 'en' => 'en', 'es' => 'es', 'fr' => 'fr', 'de' => 'de'] as $hreflang => $locale) {
        $response->assertSee('hreflang="'.$hreflang.'" href="'.route('about', ['locale' => $locale]).'"', false);
    }

    $response->assertSee('hreflang="x-default" href="'.url('/').'"', false);
});

it('noindexes starred third-party repos but not own projects', function () {
    $base = ['category' => ProjectCategory::LaravelPackage, 'status' => ProjectStatus::Published, 'published_at' => now()];
    createProject([...$base, 'name' => 'Mine', 'slug' => 'mine-pkg']);
    createProject([...$base, 'name' => 'Theirs', 'slug' => 'theirs-pkg', 'starred_at' => now()]);
    // The owner stars his own repos too — authored + maintained stay indexable.
    createProject([...$base, 'name' => 'Own Star', 'slug' => 'own-star', 'starred_at' => now(), 'github_url' => 'https://github.com/jeffersongoncalves/own-star']);
    createProject([...$base, 'name' => 'Collab', 'slug' => 'collab-star', 'starred_at' => now(), 'is_maintainer' => true]);

    $this->get('/en/projects/mine-pkg')->assertOk()->assertDontSee('noindex', false);
    $this->get('/en/projects/own-star')->assertOk()->assertDontSee('noindex', false);
    $this->get('/en/projects/collab-star')->assertOk()->assertDontSee('noindex', false);
    $this->get('/en/projects/theirs-pkg')->assertOk()->assertSee('content="noindex, follow"', false);
});

it('falls back to the English title, then a sentence, for the project description', function () {
    $base = ['category' => ProjectCategory::LaravelPackage, 'status' => ProjectStatus::Published, 'published_at' => now()];
    createProject([...$base, 'name' => 'Titled', 'slug' => 'titled-pkg', 'title' => ['en' => 'A whiteboard for sketching']]);
    createProject([...$base, 'name' => 'Bare', 'slug' => 'bare-pkg']);

    $this->get('/de/projects/titled-pkg')->assertOk()
        ->assertSee('<meta name="description" content="A whiteboard for sketching">', false);
    $this->get('/de/projects/bare-pkg')->assertOk()
        ->assertSee('<meta name="description" content="Bare — Open-Source-Projekt', false);
});

it('generates a valid XML sitemap', function () {
    $this->artisan('sitemap:generate')->assertSuccessful();

    $xml = $this->get(route('sitemap'))->assertOk()->getContent();

    expect($xml)->toContain('<urlset')
        ->not->toContain('<sitemapindex');
});

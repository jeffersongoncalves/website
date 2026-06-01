<?php

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;

it('renders home', function () {
    Project::query()->create([
        'slug' => 'sample-plugin',
        'name' => 'sample-plugin',
        'category' => ProjectCategory::FilamentPlugin,
        'versions' => ['v5'],
        'stack' => ['Filament'],
        'stars' => 10,
        'downloads' => 1000,
        'downloads_label' => '1k',
        'status' => ProjectStatus::Published,
        'featured' => true,
        'published_at' => now(),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('sample-plugin')
        ->assertSee(__('site.nav.articles'));   // Articles nav link present
});

it('renders about page', function () {
    $this->get('/about')->assertOk();
});

it('renders projects index', function () {
    $this->get('/projects')->assertOk();
});

it('filters projects by category', function () {
    Project::query()->create([
        'slug' => 'a-plugin',
        'name' => 'a-plugin',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
    ]);
    Project::query()->create([
        'slug' => 'b-package',
        'name' => 'b-package',
        'category' => ProjectCategory::LaravelPackage,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
    ]);

    $this->get('/projects?cat=filament_plugin')
        ->assertOk()
        ->assertSee('a-plugin')
        ->assertDontSee('b-package');
});

it('filters projects by daily driver role', function () {
    Project::query()->create([
        'slug' => 'a-tool',
        'name' => 'a-tool',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'is_daily_driver' => true,
        'published_at' => now(),
    ]);
    Project::query()->create([
        'slug' => 'b-plugin',
        'name' => 'b-plugin',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'is_daily_driver' => false,
        'published_at' => now(),
    ]);

    $this->get('/projects?role=daily_driver')
        ->assertOk()
        ->assertSee('a-tool')
        ->assertDontSee('b-plugin');
});

it('filters projects by origin (own vs starred) and badges starred ones', function () {
    Project::query()->create([
        'slug' => 'my-own-pkg',
        'name' => 'my-own-pkg',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
    ]);
    Project::query()->create([
        'slug' => 'starred-thing',
        'name' => 'starred-thing',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'starred_at' => now(),
        'published_at' => now(),
    ]);

    $this->get('/projects?source=starred')
        ->assertOk()
        ->assertSee('starred-thing')
        ->assertDontSee('my-own-pkg')
        ->assertSee(__('site.projects.badge_starred'));

    $this->get('/projects?source=own')
        ->assertOk()
        ->assertSee('my-own-pkg')
        ->assertDontSee('starred-thing');
});

it('renders project show', function () {
    Project::query()->create([
        'slug' => 'my-plugin',
        'name' => 'my-plugin',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
    ]);

    $this->get('/projects/my-plugin')
        ->assertOk()
        ->assertSee('my-plugin');
});

it('gives an article an article- slug and renders it as an external link card', function () {
    $project = Project::query()->create([
        'name' => 'Automate your PHP security updates',
        'category' => ProjectCategory::Article,
        'status' => ProjectStatus::Published,
        'docs_url' => 'https://yoeri.me/blog/automate-your-php-security-updates',
        'published_at' => now(),
    ]);

    expect($project->slug)->toBe('article-automate-your-php-security-updates');

    $this->get('/projects/'.$project->slug)
        ->assertOk()
        // Renders the external "visit" card (host + deep link), not a README.
        ->assertSee('yoeri.me')
        ->assertSee('https://yoeri.me/blog/automate-your-php-security-updates', false);
});

it('renders the article body as content and emits Article JSON-LD', function () {
    $project = Project::query()->create([
        'name' => 'Automate your PHP security updates',
        'category' => ProjectCategory::Article,
        'status' => ProjectStatus::Published,
        'docs_url' => 'https://yoeri.me/blog/automate-your-php-security-updates',
        'content' => ['pt' => "## Why patch\n\nKeep deps current to avoid CVEs."],
        'social_image' => 'https://yoeri.me/og/automate.png',
        'published_at' => now(),
    ]);

    $this->get('/projects/'.$project->slug)
        ->assertOk()
        ->assertSee('Why patch')                          // rendered markdown body
        ->assertSee(__('site.projects.article_read'))     // "read article" CTA
        ->assertSee('"@type":"Article"', false)           // correct JSON-LD type
        ->assertSee('https://yoeri.me/og/automate.png', false); // per-article og:image
});

it('404s on draft project show', function () {
    Project::query()->create([
        'slug' => 'draft-plugin',
        'name' => 'draft-plugin',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Draft,
    ]);

    $this->get('/projects/draft-plugin')->assertNotFound();
});

it('renders the articles index listing only articles, newest first', function () {
    Project::query()->create([
        'name' => 'Older Post',
        'category' => ProjectCategory::Article,
        'status' => ProjectStatus::Published,
        'docs_url' => 'https://blog.test/older',
        'published_at' => now()->subDays(5),
    ]);
    Project::query()->create([
        'name' => 'Newer Post',
        'category' => ProjectCategory::Article,
        'status' => ProjectStatus::Published,
        'docs_url' => 'https://blog.test/newer',
        'published_at' => now(),
    ]);
    Project::query()->create([
        'slug' => 'a-plugin-not-article',
        'name' => 'a-plugin-not-article',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
    ]);

    $response = $this->get('/articles')->assertOk()
        ->assertSee('Newer Post')
        ->assertSee('Older Post')
        ->assertDontSee('a-plugin-not-article');

    // Newest first.
    expect(strpos($response->getContent(), 'Newer Post'))
        ->toBeLessThan(strpos($response->getContent(), 'Older Post'));
});

it('serves a valid RSS feed of articles', function () {
    Project::query()->create([
        'name' => 'Feed Post',
        'category' => ProjectCategory::Article,
        'status' => ProjectStatus::Published,
        'docs_url' => 'https://blog.test/feed-post',
        'title' => ['pt' => 'Resumo do post'],
        'published_at' => now(),
    ]);

    $response = $this->get('/articles/feed')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/rss+xml');
    $response->assertSee('<rss version="2.0"', false)
        ->assertSee('<title>Feed Post</title>', false)
        ->assertSee('Resumo do post', false);
});

it('renders open-source page', function () {
    $this->get('/open-source')->assertOk();
});

it('renders sponsors page', function () {
    $this->get('/sponsors')->assertOk();
});

it('serves the service worker as javascript with no-cache headers', function () {
    $response = $this->get('/sw.js');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=utf-8')
        ->assertHeader('Service-Worker-Allowed', '/');

    expect($response->headers->get('Cache-Control'))->toContain('no-cache');
    expect($response->getContent())->toContain('const VERSION = ');
    expect($response->getContent())->toContain('OFFLINE_URL');
    // Update flow — must postMessage clients after activate so the page
    // can decide whether to surface the update toast.
    expect($response->getContent())->toContain("type: 'pwa-updated'");
});

it('renders the offline fallback page', function () {
    $this->get('/offline')
        ->assertOk()
        ->assertSeeText(__('site.offline.retry'));
});

it('serves the PWA manifest with the spec content-type, an id, and a 512 icon', function () {
    $response = $this->get('/manifest.json');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json');

    $manifest = $response->json();

    expect($manifest['id'])->toBe('/');
    expect($manifest['start_url'])->toBe('/?source=pwa');
    expect($manifest['display'])->toBe('standalone');
    expect($manifest['theme_color'])->toBe('#0B0A09');

    $sizes = array_column($manifest['icons'], 'sizes');
    expect($sizes)->toContain('192x192');
    expect($sizes)->toContain('512x512');

    $maskable = array_filter(
        $manifest['icons'],
        fn ($icon) => ($icon['purpose'] ?? null) === 'maskable',
    );
    expect($maskable)->not->toBeEmpty();
});

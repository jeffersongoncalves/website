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
        ->assertSee('sample-plugin');
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

it('404s on draft project show', function () {
    Project::query()->create([
        'slug' => 'draft-plugin',
        'name' => 'draft-plugin',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Draft,
    ]);

    $this->get('/projects/draft-plugin')->assertNotFound();
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

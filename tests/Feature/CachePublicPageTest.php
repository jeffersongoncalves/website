<?php

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Http\Middleware\CachePublicPage;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    config(['filakit.page_cache_enabled' => true]);
    Cache::flush();
});

it('serves a MISS then a HIT for the same public page', function (): void {
    $first = $this->get('/about');
    $first->assertOk()->assertHeader('X-Page-Cache', 'MISS');

    $second = $this->get('/about');
    $second->assertOk()->assertHeader('X-Page-Cache', 'HIT');
});

it('busts the cache when a project changes', function (): void {
    $this->get('/projects')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/projects')->assertHeader('X-Page-Cache', 'HIT');

    // Any project mutation bumps the page-cache version via ProjectObserver.
    Project::query()->create([
        'slug' => 'fresh',
        'name' => 'fresh-project',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
    ]);

    $this->get('/projects')
        ->assertHeader('X-Page-Cache', 'MISS')
        ->assertSee('fresh-project');
});

it('busts the cache on a new app version (deploy)', function (): void {
    config(['app.version' => '1.0.0']);
    $this->get('/about')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/about')->assertHeader('X-Page-Cache', 'HIT');

    // A deploy bumps APP_VERSION → different key → fresh render.
    config(['app.version' => '1.0.1']);
    $this->get('/about')->assertHeader('X-Page-Cache', 'MISS');
});

it('does not cache when disabled', function (): void {
    config(['filakit.page_cache_enabled' => false]);

    $res = $this->get('/about');
    $res->assertOk();
    expect($res->headers->get('X-Page-Cache'))->toBeNull();
});

it('flush() increments the version token', function (): void {
    Cache::forever('pages:version', 5);
    CachePublicPage::flush();

    expect((int) Cache::get('pages:version'))->toBe(6);
});

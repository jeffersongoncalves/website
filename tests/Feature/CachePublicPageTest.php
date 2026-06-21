<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use JeffersonGoncalves\PageCache\Middleware\CachePublicPage;

beforeEach(function (): void {
    config(['page-cache.enabled' => true]);
    Cache::flush();
});

it('serves a MISS then a HIT for the same public page', function (): void {
    $first = $this->get('/about');
    $first->assertOk()->assertHeader('X-Page-Cache', 'MISS');

    $second = $this->get('/about');
    $second->assertOk()->assertHeader('X-Page-Cache', 'HIT');
});

it('busts the cache when a project changes', function (): void {
    // /projects is excluded from the page cache (it's a Livewire component), so
    // assert against /about, which stays cached. A project mutation bumps the
    // shared page-cache version via ProjectObserver and invalidates every page.
    $this->get('/about')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/about')->assertHeader('X-Page-Cache', 'HIT');

    Project::query()->create([
        'slug' => 'fresh',
        'name' => 'fresh-project',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
    ]);

    $this->get('/about')->assertHeader('X-Page-Cache', 'MISS');
});

it('does not cache when disabled', function (): void {
    config(['page-cache.enabled' => false]);

    $res = $this->get('/about');
    $res->assertOk();
    expect($res->headers->get('X-Page-Cache'))->toBeNull();
});

it('flush() increments the version token', function (): void {
    Cache::forever('pages:version', 5);
    CachePublicPage::flush();

    expect((int) Cache::get('pages:version'))->toBe(6);
});

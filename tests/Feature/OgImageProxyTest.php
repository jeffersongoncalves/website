<?php

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    // Drop the global Pest fake so our opengraph stub wins.
    app()->forgetInstance(HttpFactory::class);
    Http::clearResolvedInstances();
    Http::preventStrayRequests();
    Cache::flush();
});

it('proxies and caches a github project social card', function () {
    $project = Project::query()->create([
        'slug' => 'jeffersongoncalves-secure-lock-cli',
        'name' => 'secure-lock-cli',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/secure-lock-cli',
        'published_at' => now(),
    ]);

    Http::fake([
        'opengraph.githubassets.com/*' => Http::response('PNGBYTES', 200, ['Content-Type' => 'image/png']),
    ]);

    $this->get('/og/'.$project->slug.'.png')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'max-age=86400, public')
        ->assertSee('PNGBYTES', false);

    // Second hit served from cache — GitHub is not called again.
    Http::fake(fn () => throw new RuntimeException('should be cached'));
    $this->get('/og/'.$project->slug.'.png')->assertOk()->assertSee('PNGBYTES', false);
});

it('redirects to the generic banner when the project has no image source', function () {
    $project = Project::query()->create([
        'slug' => 'no-image',
        'name' => 'no-image',
        'category' => ProjectCategory::Saas,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
    ]);

    $this->get('/og/'.$project->slug.'.png')->assertRedirect();
});

it('redirects to the banner when GitHub rate-limits (429)', function () {
    $project = Project::query()->create([
        'slug' => 'rate-limited',
        'name' => 'rate-limited',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/rate-limited',
        'published_at' => now(),
    ]);

    Http::fake(['opengraph.githubassets.com/*' => Http::response('Too many requests', 429)]);

    $this->get('/og/'.$project->slug.'.png')->assertRedirect();
});

it('404-falls-back for an unknown slug', function () {
    $this->get('/og/does-not-exist.png')->assertRedirect();
});

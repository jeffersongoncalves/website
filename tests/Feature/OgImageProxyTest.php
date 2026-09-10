<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    // Drop the global Pest fake so our opengraph stub wins.
    app()->forgetInstance(HttpFactory::class);
    Http::clearResolvedInstances();
    Http::preventStrayRequests();
    Storage::fake('github');
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

    Storage::disk('github')->assertExists('og-images/'.$project->slug);

    // Second hit served from disk — GitHub is not called again.
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

    // "Redirect to the banner" is now a direct 200 with the banner's own
    // bytes — WhatsApp/Facebook's crawler frequently fails to follow a
    // redirect on og:image, silently dropping the preview image.
    $this->get('/og/'.$project->slug.'.png')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
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

    $this->get('/og/'.$project->slug.'.png')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('serves a stale disk copy when GitHub rate-limits on a refresh attempt', function () {
    $project = Project::query()->create([
        'slug' => 'stale-ok',
        'name' => 'stale-ok',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/stale-ok',
        'published_at' => now(),
    ]);

    // Yesterday's successful fetch, now past the 1-day TTL.
    Storage::disk('github')->put('og-images/stale-ok', 'OLD-CARD-BYTES');
    Storage::disk('github')->put('og-images/stale-ok.type', 'image/png');
    touch(Storage::disk('github')->path('og-images/stale-ok'), now()->subDays(2)->timestamp);

    Http::fake(['opengraph.githubassets.com/*' => Http::response('Too many requests', 429)]);

    $this->get('/og/'.$project->slug.'.png')
        ->assertOk()
        ->assertSee('OLD-CARD-BYTES', false);
});

it('404-falls-back for an unknown slug', function () {
    $this->get('/og/does-not-exist.png')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('proxies a social_image hosted on a public address', function () {
    $project = Project::query()->create([
        'slug' => 'public-card',
        'name' => 'public-card',
        'category' => ProjectCategory::Saas,
        'status' => ProjectStatus::Published,
        // IP literal so the public-host check needs no DNS in the test.
        'social_image' => 'https://93.184.216.34/card.png',
        'published_at' => now(),
    ]);

    Http::fake(['93.184.216.34/*' => Http::response('CARDBYTES', 200, ['Content-Type' => 'image/png'])]);

    $this->get('/og/'.$project->slug.'.png')
        ->assertOk()
        ->assertSee('CARDBYTES', false);
});

it('refuses to proxy a social_image pointing at an internal address (SSRF)', function (string $url) {
    $project = Project::query()->create([
        'slug' => 'ssrf-'.md5($url),
        'name' => 'ssrf',
        'category' => ProjectCategory::Saas,
        'status' => ProjectStatus::Published,
        'social_image' => $url,
        'published_at' => now(),
    ]);

    // No stray HTTP request is allowed to leave — the guard must short-circuit
    // to the generic banner before any fetch happens.
    $this->get('/og/'.$project->slug.'.png')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
})->with([
    'loopback' => 'http://127.0.0.1/x.png',
    'link-local metadata' => 'http://169.254.169.254/latest/meta-data/',
    'private range' => 'http://10.0.0.5/x.png',
    'non-http scheme' => 'file:///etc/passwd',
]);

<?php

declare(strict_types=1);

use App\Http\Controllers\Site\ArticlesFeedController;
use App\Http\Controllers\Site\FaviconController;
use App\Http\Controllers\Site\LlmsTxtController;
use App\Http\Controllers\Site\OfflineController;
use App\Http\Controllers\Site\OgImageController;
use App\Livewire\Site\AboutPage;
use App\Livewire\Site\ArticlesPage;
use App\Livewire\Site\HomePage;
use App\Livewire\Site\LinksPage;
use App\Livewire\Site\OpenSourcePage;
use App\Livewire\Site\ProjectShowPage;
use App\Livewire\Site\ProjectsPage;
use App\Livewire\Site\SponsorsPage;
use App\Livewire\Site\StackPage;
use Illuminate\Support\Facades\Route;
use JeffersonGoncalves\PageCache\Middleware\CachePublicPage;
use JeffersonGoncalves\SecurityHeaders\Middleware\SecurityHeaders;

// Root-level routes (no locale prefix). Still wrapped in SecurityHeaders so
// these endpoints — especially /og and /favicon-proxy, which relay externally
// sourced bytes — get X-Content-Type-Options: nosniff and the rest.
Route::middleware([SecurityHeaders::class])->group(function () {
    // `/offline` is pre-cached by the service worker and served as the fallback
    // for navigation failures. The `/sw.js` route itself is registered by
    // jeffersongoncalves/laravel-pwa-service-worker (config pwa-service-worker.*),
    // with SecurityHeaders attached via that package's `middleware` config.
    Route::get('/offline', OfflineController::class)->name('pwa.offline');

    // Cached social-card proxy. Outside the locale/page-cache group: it's a
    // binary response and the image is locale-independent.
    Route::get('/og/{slug}.png', OgImageController::class)->name('og.show')
        ->middleware('throttle:60,1');

    // Same-origin favicon proxy for external-link cards (keeps the browser off
    // Google's S2 service). Locale-independent binary response, like /og.
    Route::get('/favicon-proxy', FaviconController::class)->name('favicon.proxy')
        ->middleware('throttle:120,1');

    // llms.txt — plain-text site map for LLM crawlers (llmstxt.org). Lives at
    // the site root with no locale prefix; it's its own cached text body.
    Route::get('/llms.txt', LlmsTxtController::class)->name('llms');
});

Route::middleware([SecurityHeaders::class, 'set.locale', CachePublicPage::class])->group(function () {
    Route::get('/', HomePage::class)->name('home');
    Route::get('/about', AboutPage::class)->name('about');

    Route::get('/projects', ProjectsPage::class)->name('projects.index')
        ->withoutMiddleware(CachePublicPage::class);
    Route::get('/projects/{slug}', ProjectShowPage::class)->name('projects.show')
        ->withoutMiddleware(CachePublicPage::class);

    Route::get('/articles', ArticlesPage::class)->name('articles.index')
        ->withoutMiddleware(CachePublicPage::class);
    Route::get('/articles/feed', ArticlesFeedController::class)->name('articles.feed')
        ->middleware('throttle:60,1');
    // Articles are Project rows but live under /articles/{slug} so the Articles
    // nav highlights instead of Projects. ProjectShowPage 301s any project to
    // its canonical section. Registered after /articles/feed so the static
    // segment still wins.
    Route::get('/articles/{slug}', ProjectShowPage::class)->name('articles.show')
        ->withoutMiddleware(CachePublicPage::class);

    Route::get('/links', LinksPage::class)->name('links.index')
        ->withoutMiddleware(CachePublicPage::class);
    // External-link projects (sites, channels, learning resources, awesome
    // lists) are canonical under /links/{slug}; ProjectShowPage 301s any served
    // under the wrong section. Registered after the static /links route.
    Route::get('/links/{slug}', ProjectShowPage::class)->name('links.show')
        ->withoutMiddleware(CachePublicPage::class);

    Route::get('/open-source', OpenSourcePage::class)->name('open-source');

    Route::get('/stack', StackPage::class)->name('stack');

    Route::get('/sponsors', SponsorsPage::class)->name('sponsors');
});

// /locale/{locale} (name `locale.switch`) is registered by
// jeffersongoncalves/laravel-locale-cookie (config locale-cookie.switch).

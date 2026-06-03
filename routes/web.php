<?php

use App\Http\Controllers\Site\ArticlesFeedController;
use App\Http\Controllers\Site\LlmsTxtController;
use App\Http\Controllers\Site\OfflineController;
use App\Http\Controllers\Site\OgImageController;
use App\Http\Controllers\Site\ServiceWorkerController;
use App\Http\Controllers\Site\SwitchLocaleController;
use App\Http\Middleware\CachePublicPage;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
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

// PWA infrastructure. `/sw.js` must live at the site root (not behind the
// locale middleware) so the service worker scope is `/` and there's no
// locale-prefixed redirect competing with the registration. `/offline` is
// pre-cached by the SW and served as the fallback for navigation failures.
Route::get('/sw.js', ServiceWorkerController::class)->name('pwa.sw');
Route::get('/offline', OfflineController::class)->name('pwa.offline');

// Cached social-card proxy. Outside the locale/page-cache group: it's a binary
// response and the image is locale-independent.
Route::get('/og/{slug}.png', OgImageController::class)->name('og.show');

// llms.txt — plain-text site map for LLM crawlers (llmstxt.org). Lives at the
// site root with no locale prefix, like /sw.js; it's its own cached text body.
Route::get('/llms.txt', LlmsTxtController::class)->name('llms');

Route::middleware([SecurityHeaders::class, 'set.locale', CachePublicPage::class])->group(function () {
    Route::get('/', HomePage::class)->name('home');
    Route::get('/about', AboutPage::class)->name('about');

    Route::get('/projects', ProjectsPage::class)->name('projects.index')
        ->withoutMiddleware(CachePublicPage::class);
    Route::get('/projects/{slug}', ProjectShowPage::class)->name('projects.show')
        ->withoutMiddleware(CachePublicPage::class);

    Route::get('/articles', ArticlesPage::class)->name('articles.index')
        ->withoutMiddleware(CachePublicPage::class);
    Route::get('/articles/feed', ArticlesFeedController::class)->name('articles.feed');
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

    Route::get('/locale/{locale}', SwitchLocaleController::class)
        ->whereIn('locale', SetLocale::SUPPORTED)
        ->name('locale.switch');
});

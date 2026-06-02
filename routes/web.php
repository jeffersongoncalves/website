<?php

use App\Http\Controllers\Site\AboutController;
use App\Http\Controllers\Site\ArticlesController;
use App\Http\Controllers\Site\ArticlesFeedController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\LinksController;
use App\Http\Controllers\Site\OfflineController;
use App\Http\Controllers\Site\OgImageController;
use App\Http\Controllers\Site\OpenSourceController;
use App\Http\Controllers\Site\ProjectController;
use App\Http\Controllers\Site\ProjectViewController;
use App\Http\Controllers\Site\ServiceWorkerController;
use App\Http\Controllers\Site\SponsorsController;
use App\Http\Controllers\Site\StackController;
use App\Http\Controllers\Site\SwitchLocaleController;
use App\Http\Middleware\CachePublicPage;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
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

Route::middleware([SecurityHeaders::class, 'set.locale', CachePublicPage::class])->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/about', AboutController::class)->name('about');

    Route::get('/projects', ProjectController::class)->name('projects.index');
    Route::get('/projects/{slug}', ProjectViewController::class)->name('projects.show');

    Route::get('/articles', ArticlesController::class)->name('articles.index');
    Route::get('/articles/feed', ArticlesFeedController::class)->name('articles.feed');
    // Articles share ProjectViewController (they're Project rows) but live under
    // /articles/{slug} so the Articles nav highlights instead of Projects. The
    // controller 301s any project to its canonical section. Registered after
    // /articles/feed so the static segment still wins.
    Route::get('/articles/{slug}', ProjectViewController::class)->name('articles.show');

    Route::get('/links', LinksController::class)->name('links.index');
    // External-link projects (sites, channels, learning resources, awesome
    // lists) are canonical under /links/{slug}; ProjectViewController 301s any
    // served under the wrong section. Registered after the static /links route.
    Route::get('/links/{slug}', ProjectViewController::class)->name('links.show');

    Route::get('/open-source', OpenSourceController::class)->name('open-source');

    Route::get('/stack', StackController::class)->name('stack');

    Route::get('/sponsors', SponsorsController::class)->name('sponsors');

    Route::get('/locale/{locale}', SwitchLocaleController::class)
        ->whereIn('locale', SetLocale::SUPPORTED)
        ->name('locale.switch');
});

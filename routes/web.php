<?php

use App\Http\Controllers\Site\AboutController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\OfflineController;
use App\Http\Controllers\Site\OpenSourceController;
use App\Http\Controllers\Site\ProjectController;
use App\Http\Controllers\Site\ProjectViewController;
use App\Http\Controllers\Site\ServiceWorkerController;
use App\Http\Controllers\Site\SponsorsController;
use App\Http\Controllers\Site\SwitchLocaleController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

// PWA infrastructure. `/sw.js` must live at the site root (not behind the
// locale middleware) so the service worker scope is `/` and there's no
// locale-prefixed redirect competing with the registration. `/offline` is
// pre-cached by the SW and served as the fallback for navigation failures.
Route::get('/sw.js', ServiceWorkerController::class)->name('pwa.sw');
Route::get('/offline', OfflineController::class)->name('pwa.offline');

Route::middleware('set.locale')->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/about', AboutController::class)->name('about');

    Route::get('/projects', ProjectController::class)->name('projects.index');
    Route::get('/projects/{slug}', ProjectViewController::class)->name('projects.show');

    Route::get('/open-source', OpenSourceController::class)->name('open-source');

    Route::get('/sponsors', SponsorsController::class)->name('sponsors');

    Route::get('/locale/{locale}', SwitchLocaleController::class)
        ->whereIn('locale', SetLocale::SUPPORTED)
        ->name('locale.switch');
});

<?php

use App\Http\Controllers\Site\AboutController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\OfflineController;
use App\Http\Controllers\Site\OpenSourceController;
use App\Http\Controllers\Site\ProjectController;
use App\Http\Controllers\Site\ProjectViewController;
use App\Http\Controllers\Site\PushSubscriptionController;
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

// Push subscription endpoints. POST-only, CSRF token comes from the
// `<meta name="csrf-token">` already in the layout. Kept outside the
// locale middleware so the browser doesn't get bounced to a localised URL
// when posting from the service worker context.
Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('pwa.push.subscribe');
Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('pwa.push.unsubscribe');

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

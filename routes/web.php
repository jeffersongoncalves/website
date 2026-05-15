<?php

use App\Http\Controllers\Site\AboutController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\OpenSourceController;
use App\Http\Controllers\Site\ProjectController;
use App\Http\Controllers\Site\ProjectViewController;
use App\Http\Controllers\Site\SponsorsController;
use App\Http\Controllers\Site\SwitchLocaleController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

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

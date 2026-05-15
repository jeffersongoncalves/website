<?php

use App\Http\Controllers\Site\AboutController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\OpenSourceController;
use App\Http\Controllers\Site\ProjectController;
use App\Http\Controllers\Site\ProjectViewController;
use App\Http\Controllers\Site\SponsorsController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $locale = request()->cookie('locale', config('app.locale', 'pt'));
    $locale = in_array($locale, SetLocale::SUPPORTED, true) ? $locale : 'pt';

    return redirect("/{$locale}");
});

Route::prefix('{locale}')
    ->where(['locale' => implode('|', SetLocale::SUPPORTED)])
    ->middleware('set.locale')
    ->group(function () {
        Route::get('/', HomeController::class)->name('home');
        Route::get('/about', AboutController::class)->name('about');

        Route::get('/projects', ProjectController::class)->name('projects.index');
        Route::get('/projects/{slug}', ProjectViewController::class)->name('projects.show');

        Route::get('/open-source', OpenSourceController::class)->name('open-source');

        Route::get('/sponsors', SponsorsController::class)->name('sponsors');
    });

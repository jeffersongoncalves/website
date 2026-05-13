<?php

use App\Http\Controllers\Site\AboutController;
use App\Http\Controllers\Site\BlogController;
use App\Http\Controllers\Site\ContactController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\OpenSourceController;
use App\Http\Controllers\Site\ProjectController;
use App\Http\Controllers\Site\SponsorsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $locale = request()->cookie('locale', config('app.locale', 'pt'));
    $locale = in_array($locale, ['pt', 'en'], true) ? $locale : 'pt';

    return redirect("/{$locale}");
});

Route::prefix('{locale}')
    ->where(['locale' => 'pt|en'])
    ->middleware('set.locale')
    ->group(function () {
        Route::get('/', [HomeController::class, 'index'])->name('home');
        Route::get('/sobre', [AboutController::class, 'index'])->name('about');

        Route::get('/projetos', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('/projetos/{slug}', [ProjectController::class, 'show'])->name('projects.show');

        Route::get('/open-source', [OpenSourceController::class, 'index'])->name('open-source');

        Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
        Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

        Route::get('/sponsors', [SponsorsController::class, 'index'])->name('sponsors');

        Route::get('/contato', [ContactController::class, 'show'])->name('contact');
        Route::post('/contato', [ContactController::class, 'submit'])->name('contact.submit');
    });

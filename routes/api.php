<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PluginsSyncController;
use Illuminate\Support\Facades\Route;
use JeffersonGoncalves\SecurityHeaders\Middleware\SecurityHeaders;

// Called by jeffersongoncalves/jeffersongoncalves's notify-site-plugins-sync
// workflow whenever plugins.json changes. Bearer-token protected inside the
// controller; throttled like the app's other public relay endpoints.
Route::post('/plugins-sync', PluginsSyncController::class)
    ->middleware([SecurityHeaders::class, 'throttle:10,1'])
    ->name('api.plugins-sync');

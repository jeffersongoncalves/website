<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use JeffersonGoncalves\LocaleCookie\Middleware\SetLocale;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'set.locale' => SetLocale::class,
        ]);

        // The theme cookie is written by JS on the client (no PHP touchpoint),
        // so EncryptCookies must skip it — otherwise the decrypt step strips
        // the plain "dark"/"light" value before Blade can read it back.
        $middleware->encryptCookies(except: ['theme']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

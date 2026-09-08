<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use JeffersonGoncalves\LocaleCookie\Middleware\SetLocale;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'set.locale' => SetLocale::class,
        ]);

        // The site is served through Cloudflare, so every request arrives from
        // an edge IP. Without this, request()->ip() is Cloudflare's address for
        // all traffic — throttle:60,1 becomes one global bucket and short-url
        // visit tracking hashes the same "visitor" for everyone.
        //
        // Scoped to Cloudflare's published ranges rather than '*': the origin
        // answers on its own address too, and a wildcard would let anyone forge
        // X-Forwarded-For by hitting it directly. Refresh from
        // https://www.cloudflare.com/ips-v4 + /ips-v6 (list last synced
        // 2026-08-23; it changes rarely).
        $middleware->trustProxies(at: [
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
            '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
            '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
            '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
        ]);

        // The theme cookie is written by JS on the client (no PHP touchpoint),
        // so EncryptCookies must skip it — otherwise the decrypt step strips
        // the plain "dark"/"light" value before Blade can read it back.
        $middleware->encryptCookies(except: ['theme']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

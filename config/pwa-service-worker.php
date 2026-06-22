<?php

declare(strict_types=1);
use JeffersonGoncalves\SecurityHeaders\Middleware\SecurityHeaders;

return [
    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | When false the /sw.js route is not registered at all.
    |
    */
    'enabled' => env('PWA_SW_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Route Path
    |--------------------------------------------------------------------------
    |
    | Path the service worker is served from. Keep it at the site root so the
    | SW scope can be `/` (a deeper path narrows the scope).
    |
    */
    'path' => env('PWA_SW_PATH', 'sw.js'),

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    |
    | SecurityHeaders so the worker response carries the same hardening (nosniff
    | etc) the manual route used to apply. The package registers /sw.js at the
    | site root (no locale prefix) with this middleware attached.
    |
    */
    'middleware' => [SecurityHeaders::class],

    /*
    |--------------------------------------------------------------------------
    | View
    |--------------------------------------------------------------------------
    |
    | The Blade view rendered as the service-worker body. Publish the package
    | view and point this at your own copy to customise the strategies.
    |
    */
    'view' => 'pwa-service-worker::sw',

    /*
    |--------------------------------------------------------------------------
    | Build Manifest
    |--------------------------------------------------------------------------
    |
    | Path to the Vite build manifest. Its md5 seeds the SW cache version, so a
    | rebuild busts the cache. Falls back to a "dev" version when absent.
    |
    */
    'build_manifest' => public_path('build/manifest.json'),

    /*
    |--------------------------------------------------------------------------
    | Cache Prefix
    |--------------------------------------------------------------------------
    |
    | The Cache Storage name is `{cache_prefix}-v{version}`. On activate, caches
    | starting with `{cache_prefix}-` other than the current one are deleted.
    |
    */
    'cache_prefix' => env('PWA_SW_CACHE_PREFIX', 'jg-pwa'),

    /*
    |--------------------------------------------------------------------------
    | Offline Fallback URL
    |--------------------------------------------------------------------------
    |
    | Served for a failed navigation when nothing is cached. You must register
    | this route yourself (it is intentionally not provided by the package).
    |
    */
    'offline_url' => env('PWA_SW_OFFLINE_URL', '/offline'),

    /*
    |--------------------------------------------------------------------------
    | Precache URLs
    |--------------------------------------------------------------------------
    |
    | Seeded into the cache on install so a cold start works offline.
    |
    */
    'precache_urls' => ['/offline', '/', '/?source=pwa'],

    /*
    |--------------------------------------------------------------------------
    | Passthrough
    |--------------------------------------------------------------------------
    |
    | Requests that must always hit the network. `passthrough_prefixes` matches
    | a path prefix; `passthrough_exact` matches the whole path. Admin/Livewire/
    | API routes and the SW + manifest themselves belong here.
    |
    */
    'passthrough_prefixes' => ['/admin', '/app', '/livewire', '/api', '/horizon', '/telescope', '/pulse'],

    'passthrough_exact' => ['/sw.js', '/manifest.json'],

    /*
    |--------------------------------------------------------------------------
    | Asset Prefix
    |--------------------------------------------------------------------------
    |
    | Path prefix of content-hashed (immutable) build assets, served cache-first.
    |
    */
    'asset_prefix' => '/build/',
];

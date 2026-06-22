<?php

declare(strict_types=1);
use JeffersonGoncalves\SecurityHeaders\Middleware\SecurityHeaders;

return [

    /*
    |--------------------------------------------------------------------------
    | Cookie Name
    |--------------------------------------------------------------------------
    |
    | The name of the cookie the middleware reads the visitor's locale from.
    |
    */

    'cookie' => 'locale',

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | The whitelist of locales the application accepts. The cookie value is
    | validated against this list; any value that is not present here is
    | ignored and the fallback locale is used instead.
    |
    */

    'supported' => ['pt_BR', 'en', 'es'],

    /*
    |--------------------------------------------------------------------------
    | Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The locale applied when the cookie is missing or holds an unsupported
    | value. When left as null, the middleware falls back to the framework's
    | own config('app.fallback_locale').
    |
    */

    'fallback' => 'pt_BR',

    /*
    |--------------------------------------------------------------------------
    | Locale Switch Route
    |--------------------------------------------------------------------------
    |
    | The package registers `locale/{locale}` (name `locale.switch`) — persists
    | the chosen locale in the cookie and redirects to a same-host Referer. The
    | header switcher links to route('locale.switch', ['locale' => $code]).
    | `web` keeps the cookie encrypted like SetLocale reads it; SecurityHeaders
    | mirrors the hardening the manual route used to apply.
    |
    */

    'switch' => [
        'enabled' => true,
        'path' => 'locale/{locale}',
        'name' => 'locale.switch',
        'lifetime' => 60 * 24 * 365,
        'middleware' => ['web', SecurityHeaders::class],
    ],

];

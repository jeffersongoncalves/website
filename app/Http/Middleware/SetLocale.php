<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['pt', 'en', 'es'];

    /** URL/cookie segment => internal locale */
    public const URL_TO_LOCALE = [
        'pt' => 'pt_BR',
        'en' => 'en',
        'es' => 'es',
    ];

    public const COOKIE_NAME = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        $segment = $request->cookie(self::COOKIE_NAME, 'pt');

        if (! in_array($segment, self::SUPPORTED, true)) {
            $segment = 'pt';
        }

        App::setLocale(self::URL_TO_LOCALE[$segment]);

        return $next($request);
    }
}

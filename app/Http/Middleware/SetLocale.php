<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['pt', 'en'];

    public const COOKIE_NAME = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = $request->cookie(self::COOKIE_NAME, config('app.locale', 'pt'));
            $locale = in_array($locale, self::SUPPORTED, true) ? $locale : 'pt';
        }

        App::setLocale($locale);

        $response = $next($request);

        Cookie::queue(self::COOKIE_NAME, $locale, 60 * 24 * 365);

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the app locale from the `{locale}` route segment (see
 * routes/web.php's `Route::prefix('{locale}')` group) instead of
 * jeffersongoncalves/laravel-locale-cookie's own SetLocale, which only reads
 * a static per-group route action — every page here carries a dynamic
 * `{locale}` URL segment instead, so the locale has to come from the matched
 * route's parameter, not an action.
 *
 * Falls back to the cookie when there's no `{locale}` route param at all —
 * this is what keeps Livewire's `/livewire/update` requests (registered as a
 * persistent middleware in AppServiceProvider) rendering in the visitor's
 * locale instead of resetting to the fallback mid-interaction, since that
 * route has no `{locale}` segment of its own.
 */
class SetLocaleFromRoute
{
    public function handle(Request $request, Closure $next): Response
    {
        $cookieName = config('locale-cookie.cookie', 'locale');
        $supported = (array) config('locale-cookie.supported', ['en']);
        $fallback = config('locale-cookie.fallback') ?? config('app.fallback_locale') ?? 'en';

        $routeLocale = $request->route('locale');

        if (is_string($routeLocale) && in_array($routeLocale, $supported, true)) {
            $locale = $routeLocale;
        } else {
            $locale = $request->cookies->get($cookieName);

            if (! is_string($locale) || ! in_array($locale, $supported, true)) {
                $locale = $fallback;
            }
        }

        App::setLocale($locale);

        // So route()/URL generation during this request defaults to the
        // resolved locale without every call site passing it explicitly.
        URL::defaults(['locale' => $locale]);

        if (is_string($routeLocale) && $routeLocale === $locale && $request->cookies->get($cookieName) !== $locale) {
            Cookie::queue($cookieName, $locale, (int) config('locale-cookie.switch.lifetime', 60 * 24 * 365), '/', null, (bool) config('session.secure', false), false, false, 'lax');
        }

        return $next($request);
    }
}

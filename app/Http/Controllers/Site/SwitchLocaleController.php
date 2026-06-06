<?php

namespace App\Http\Controllers\Site;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class SwitchLocaleController
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        if (! in_array($locale, SetLocale::SUPPORTED, true)) {
            $locale = 'pt';
        }

        Cookie::queue(SetLocale::COOKIE_NAME, $locale, 60 * 24 * 365);

        return redirect($this->safeReferer($request));
    }

    /**
     * The Referer is attacker-controllable, so only honour it when it points
     * back at our own host. Anything cross-origin (or unparseable) falls back
     * to the home page — this closes the open-redirect window.
     */
    private function safeReferer(Request $request): string
    {
        $referer = $request->headers->get('referer');

        if ($referer === null || $referer === '') {
            return '/';
        }

        $host = parse_url($referer, PHP_URL_HOST);

        return $host === $request->getHost() ? $referer : '/';
    }
}

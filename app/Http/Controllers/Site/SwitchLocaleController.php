<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class SwitchLocaleController
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        $supported = config('locale-cookie.supported', []);

        if (! in_array($locale, $supported, true)) {
            $locale = config('locale-cookie.fallback') ?? config('app.fallback_locale');
        }

        Cookie::queue(config('locale-cookie.cookie', 'locale'), $locale, 60 * 24 * 365);

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

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

        return redirect($request->headers->get('referer', '/'));
    }
}

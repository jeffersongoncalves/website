<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for the public site. Applied as the outermost
 * middleware of the public group so it also stamps cached (HIT) responses from
 * CachePublicPage.
 *
 * The CSP is deliberately permissive on script/style: Alpine.js evaluates
 * expressions via `new Function` (needs 'unsafe-eval') and the page ships
 * inline handlers + Google Tag Manager / gtag. The value is in the structural
 * directives — frame-ancestors (clickjacking), object-src none, base-uri and
 * form-action lock-down, and upgrade-insecure-requests.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), browsing-topics=()');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());

        // HSTS only over real HTTPS and never in local dev (a cached max-age on
        // a *.test domain is a pain to undo).
        if ($request->secure() && ! app()->environment('local')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.googletagmanager.com https://www.google-analytics.com",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self' https://www.google-analytics.com https://*.google-analytics.com https://*.analytics.google.com https://www.googletagmanager.com",
            "frame-src 'self' https://www.googletagmanager.com",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
            'upgrade-insecure-requests',
        ]);
    }
}

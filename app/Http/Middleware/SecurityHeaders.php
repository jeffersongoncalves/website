<?php

declare(strict_types=1);

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
 * inline handlers + Google Tag Manager / gtag + Cloudflare Web Analytics
 * (edge-injected beacon.min.js). The value is in the structural
 * directives — frame-ancestors (clickjacking), object-src none, base-uri and
 * form-action lock-down, and upgrade-insecure-requests.
 *
 * XSS NOTE: because 'unsafe-inline'/'unsafe-eval' stay (a nonce would break the
 * package-injected GTM/gtag inline scripts, and Alpine needs eval regardless),
 * the CSP is NOT the XSS backstop for the untrusted HTML this site renders
 * (third-party GitHub READMEs + imported article bodies via {!! !!}).
 * App\Support\HtmlSanitizer is the SOLE control there — it strips <script>,
 * event-handler attributes and Alpine x-* attributes. Do not weaken the
 * sanitizer or add a new {!! !!} sink for untrusted content without a
 * compensating control.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), browsing-topics=()');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());

        // Isolate the browsing context (XS-Leaks / Spectre defence-in-depth).
        // "-allow-popups" keeps analytics/GTM popups from being severed.
        // No COEP/CORP: those would break the cross-origin-embeddable OG images
        // and the `img-src https:` third-party images.
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

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
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.googletagmanager.com https://www.google-analytics.com https://static.cloudflareinsights.com",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self' https://www.google-analytics.com https://*.google-analytics.com https://*.analytics.google.com https://www.googletagmanager.com https://cloudflareinsights.com",
            "frame-src 'self' https://www.googletagmanager.com",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
            'upgrade-insecure-requests',
        ]);
    }
}

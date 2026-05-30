@props([
    'title' => null,
    'description' => null,
    'breadcrumbs' => null,
    'seoData' => null,
])
@php
    $resolvedSeo = $seoData ?? new \RalphJSmit\Laravel\SEO\Support\SEOData(
        title: $title,
        description: $description ?: __('site.seo.default_description'),
    );

    // Site defaults to dark. Only an explicit `theme=light` cookie opts out.
    // Anything else (no cookie, malformed value) is treated as dark so the
    // server can stamp the right class + bg pre-paint and avoid the
    // first-visit light-to-dark flicker.
    $themeCookie = request()->cookie('theme');
    $isDarkInitial = $themeCookie !== 'light';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      @class(['dark' => $isDarkInitial])
      style="background: {{ $isDarkInitial ? '#0B0A09' : '#FFFEF9' }}; color-scheme: {{ $isDarkInitial ? 'dark' : 'light' }};">
<head>
    <x-gtm/>
    <meta charset="UTF-8">
    {{-- viewport-fit=cover lets the page paint into the iOS safe-area
         (notch/dynamic island) when running as a standalone PWA with
         status-bar-style=black-translucent. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="{{ $isDarkInitial ? 'dark' : 'light' }}">
    <x-favicon/>
    {!! seo($resolvedSeo) !!}
    @vite(['resources/css/site.css', 'resources/js/site.js'])
    @stack('head')
</head>
<body>
    <x-gtm-noscript/>
    <a href="#top"
       class="sr-only focus:not-sr-only focus:absolute focus:z-[60] focus:top-3 focus:left-3 focus:rounded focus:px-4 focus:py-2 focus:bg-ink-900 focus:text-ink-100 focus:ring-2">@lang('site.common.skip_to_content')</a>
    @include('site.partials.header')

    <main id="top">
        {{ $slot }}
    </main>

    @include('site.partials.footer')

    {{-- PWA update toast — appears only when the service worker activates
         a new version different from the one the visitor has been running.
         Hidden on first install since the localStorage seed is empty then. --}}
    <div x-data="pwaUpdateToast()"
         x-show="open"
         x-cloak
         x-transition.opacity
         class="pwa-update-toast"
         role="status"
         aria-live="polite">
        <div class="pwa-update-toast-body">
            <span class="pwa-update-toast-pulse" aria-hidden="true"></span>
            <p class="pwa-update-toast-text">@lang('site.pwa.update.message')</p>
        </div>
        <div class="pwa-update-toast-actions">
            <button type="button" x-on:click="reload()" class="pwa-update-toast-btn">
                @lang('site.pwa.update.reload')
            </button>
            <button type="button"
                    x-on:click="dismiss()"
                    class="pwa-update-toast-dismiss"
                    :aria-label="@js(__('site.pwa.update.dismiss'))">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
    </div>

    @stack('scripts')
</body>
</html>

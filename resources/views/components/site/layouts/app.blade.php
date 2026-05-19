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
    @include('site.partials.header')

    <main id="top">
        {{ $slot }}
    </main>

    @include('site.partials.footer')

    @stack('scripts')
</body>
</html>

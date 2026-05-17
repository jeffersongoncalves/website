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

    $themeCookie = request()->cookie('theme'); // 'dark' | 'light' | null
    $hasExplicitTheme = in_array($themeCookie, ['dark', 'light'], true);
    $isDarkInitial = $themeCookie === 'dark';
    $isLightInitial = $themeCookie === 'light';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      @class(['dark' => $isDarkInitial])
      style="background: {{ $isDarkInitial ? '#0B0A09' : ($isLightInitial ? '#FFFEF9' : 'transparent') }}; color-scheme: {{ $isDarkInitial ? 'dark' : ($isLightInitial ? 'light' : 'light dark') }};">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    {{-- Inline pre-paint defaults that follow system pref when there is no
         cookie yet. Once a cookie is set, the server already stamped the
         right class + bg above so this <style> block has nothing left to
         do for that visitor. --}}
    @unless($hasExplicitTheme)
        <style>
            html { background: #FFFEF9; }
            @media (prefers-color-scheme: dark) {
                html { background: #0B0A09; }
            }
        </style>
    @endunless
    <x-favicon/>
    <script>
        (function () {
            try {
                var hasCookie = document.cookie.split('; ').some(function (c) { return c.indexOf('theme=') === 0; });
                if (hasCookie) return; // server already resolved the theme
                var dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                var h = document.documentElement;
                if (dark) h.classList.add('dark');
                var tcm = document.getElementById('theme-color-meta');
                if (tcm) tcm.content = dark ? '#0B0A09' : '#FFFEF9';
            } catch (e) {}
        })();
    </script>
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

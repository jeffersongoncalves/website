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
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    {{-- Pre-paint theme resolver: applies html.dark before CSS loads to avoid FOUC --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('theme');
                var dark = t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches);
                if (dark) document.documentElement.classList.add('dark');
            } catch (e) {}
        })();
    </script>
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

<link rel="apple-touch-icon" sizes="57x57" href="{{ Vite::asset('resources/favicon/apple-icon-57x57.png') }}">
<link rel="apple-touch-icon" sizes="60x60" href="{{ Vite::asset('resources/favicon/apple-icon-60x60.png') }}">
<link rel="apple-touch-icon" sizes="72x72" href="{{ Vite::asset('resources/favicon/apple-icon-72x72.png') }}">
<link rel="apple-touch-icon" sizes="76x76" href="{{ Vite::asset('resources/favicon/apple-icon-76x76.png') }}">
<link rel="apple-touch-icon" sizes="120x120" href="{{ Vite::asset('resources/favicon/apple-icon-120x120.png') }}">
<link rel="apple-touch-icon" sizes="152x152" href="{{ Vite::asset('resources/favicon/apple-icon-152x152.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ Vite::asset('resources/favicon/apple-icon-180x180.png') }}">
<link rel="icon" type="image/png" sizes="192x192"
      href="{{ Vite::asset('resources/favicon/android-icon-192x192.png') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ Vite::asset('resources/favicon/favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="96x96" href="{{ Vite::asset('resources/favicon/favicon-96x96.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ Vite::asset('resources/favicon/favicon-16x16.png') }}">
<link rel="manifest" href="{{ asset('/manifest.json') }}">
<meta name="msapplication-TileColor" content="#ffffff">
<meta name="msapplication-TileImage" content="{{ Vite::asset('resources/favicon/ms-icon-144x144.png') }}">
@php
    // Match the site's default-dark theme: dark unless an explicit `light`
    // cookie is set. Keeps browser chrome (mobile address bar) in sync with
    // the pre-paint bg stamped in components.site.layouts.app.
    $themeColorMetaInitial = request()->cookie('theme') === 'light' ? '#FFFEF9' : '#0B0A09';
@endphp
<meta name="theme-color" id="theme-color-meta" content="{{ $themeColorMetaInitial }}">
<meta name="mobile-web-app-capable" content="yes"/>
<meta name="apple-mobile-web-app-capable" content="yes"/>
<meta name="apple-mobile-web-app-title" content="{{ config('pwa-favicon.manifest.short_name', config('pwa-favicon.manifest.name')) }}"/>
{{-- `black-translucent` lets the page paint behind the iOS status bar so the
     dark theme bg covers the area — `black` would otherwise leave a flat
     black strip even in light mode. --}}
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"/>

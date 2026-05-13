<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($title ?? '') ? ($title . ' · ') : '' }}{{ config('app.name') }}</title>
    <x-favicon/>
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

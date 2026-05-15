<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('code') · {{ config('app.name') }}</title>
    <x-favicon/>
    @vite('resources/css/site.css')
    <style>html,body{margin:0;background:#0B0A09;}</style>
</head>
<body>
    <main class="error-page">
        <div class="error-inner">
            <p class="eyebrow">@yield('label', __('site.errors.label'))</p>
            <p class="error-code">@yield('code')</p>
            <h1 class="h-section">@yield('title')</h1>
            <p class="body-text error-message">@yield('message')</p>
            <a href="{{ url('/') }}" class="btn-ghost mono error-home">← @lang('site.errors.home')</a>
        </div>
    </main>
</body>
</html>

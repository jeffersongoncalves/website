<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $screen }} — {{ config('app.name') }}</title>
    <x-favicon/>
    @vite(['resources/css/site/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="wrap" style="padding-top:96px;padding-bottom:96px;">
        <div class="sec-num">§ placeholder · {{ $screen }}</div>
        <h1 style="margin-top:24px;">
            {{ $screen }} <span style="color:var(--ink-400);font-weight:300;">@ {{ app()->getLocale() }}</span>
        </h1>
        <p style="margin-top:24px;">
            Rota funcionando. Conteúdo será implementado na etapa correspondente.
        </p>

        <nav style="margin-top:48px;display:flex;flex-wrap:wrap;gap:12px;">
            <a class="btn btn-secondary" href="{{ route('home', ['locale' => app()->getLocale()]) }}">{{ __('site.common.home') }}</a>
            <a class="btn btn-secondary" href="{{ route('about', ['locale' => app()->getLocale()]) }}">{{ __('site.nav.about') }}</a>
            <a class="btn btn-secondary" href="{{ route('projects.index', ['locale' => app()->getLocale()]) }}">{{ __('site.nav.projects') }}</a>
            <a class="btn btn-secondary" href="{{ route('open-source', ['locale' => app()->getLocale()]) }}">{{ __('site.nav.open_source') }}</a>
            <a class="btn btn-secondary" href="{{ route('blog.index', ['locale' => app()->getLocale()]) }}">{{ __('site.nav.blog') }}</a>
            <a class="btn btn-secondary" href="{{ route('sponsors', ['locale' => app()->getLocale()]) }}">{{ __('site.nav.sponsors') }}</a>
            <a class="btn btn-secondary" href="{{ route('contact', ['locale' => app()->getLocale()]) }}">{{ __('site.nav.contact') }}</a>
        </nav>

        <div style="margin-top:32px;display:flex;gap:8px;font-family:var(--font-mono);font-size:12px;color:var(--ink-500);">
            locale toggle:
            <a href="/pt{{ request()->path() === '/' ? '' : '/' . ltrim(str_replace('/' . app()->getLocale(), '', '/' . request()->path()), '/') }}">PT</a>
            <span>·</span>
            <a href="/en{{ request()->path() === '/' ? '' : '/' . ltrim(str_replace('/' . app()->getLocale(), '', '/' . request()->path()), '/') }}">EN</a>
        </div>
    </main>
</body>
</html>

@php
    $locale = app()->getLocale();
    $otherLocale = $locale === 'pt' ? 'en' : 'pt';
    $currentName = \Illuminate\Support\Facades\Route::currentRouteName();
    $currentParams = array_merge(request()->route()?->parameters() ?? [], ['locale' => $otherLocale]);
    $switchUrl = $currentName ? route($currentName, $currentParams) : url('/' . $otherLocale);
@endphp

<header class="site-header" x-data="stickyHeader()" :class="{ 'scrolled': scrolled }">
    <div class="wrap site-header-inner">
        <a href="{{ route('home', ['locale' => $locale]) }}" class="site-brand" aria-label="Jefferson Gonçalves — Home">
            <img src="{{ Vite::asset('resources/images/icon-32.png') }}"
                 srcset="{{ Vite::asset('resources/images/icon-32.png') }} 1x, {{ Vite::asset('resources/images/icon-64.png') }} 2x"
                 alt="" width="22" height="22" class="jg-mark-img">
            <span>Jefferson Gonçalves</span>
        </a>

        <nav class="hidden md:flex items-center gap-8 text-[0.9375rem]" aria-label="@lang('site.nav.about')">
            <a href="{{ route('about',         ['locale' => $locale]) }}" class="nav-link">@lang('site.nav.about')</a>
            <a href="{{ route('projects.index',['locale' => $locale]) }}" class="nav-link">@lang('site.nav.projects')</a>
            <a href="{{ route('open-source',   ['locale' => $locale]) }}" class="nav-link">@lang('site.nav.open_source')</a>
            <a href="{{ route('blog.index',    ['locale' => $locale]) }}" class="nav-link">@lang('site.nav.blog')</a>
            <a href="{{ route('sponsors',      ['locale' => $locale]) }}" class="nav-link">@lang('site.nav.sponsors')</a>
        </nav>

        <a href="{{ $switchUrl }}" class="lang-toggle" aria-label="@lang('site.common.toggle_lang')">
            <span class="{{ $locale === 'pt' ? 'on' : 'off' }}">PT</span>
            <span class="sep">|</span>
            <span class="{{ $locale === 'en' ? 'on' : 'off' }}">EN</span>
        </a>
    </div>
</header>

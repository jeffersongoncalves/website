@php
    $locale = app()->getLocale();
    $locales = \App\Http\Middleware\SetLocale::SUPPORTED;
    $currentName = \Illuminate\Support\Facades\Route::currentRouteName();
    $localeUrl = function (string $target) use ($currentName) {
        $params = array_merge(request()->route()?->parameters() ?? [], ['locale' => $target]);
        return $currentName ? route($currentName, $params) : url('/' . $target);
    };
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
            <a href="{{ route('sponsors',      ['locale' => $locale]) }}" class="nav-link">@lang('site.nav.sponsors')</a>
        </nav>

        <div class="lang-dropdown" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
            <button type="button"
                    class="lang-dropdown-btn"
                    @click="open = !open"
                    :aria-expanded="open.toString()"
                    aria-haspopup="listbox"
                    aria-label="@lang('site.common.toggle_lang')">
                <span>{{ strtoupper($locale) }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" :class="{ 'rotate-180': open }" style="transition:transform 200ms var(--ease-out);"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <ul class="lang-dropdown-menu"
                role="listbox"
                x-show="open"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                x-cloak>
                @foreach($locales as $code)
                    <li role="option" aria-selected="{{ $locale === $code ? 'true' : 'false' }}">
                        <a href="{{ $localeUrl($code) }}"
                           class="lang-dropdown-item {{ $locale === $code ? 'is-active' : '' }}">
                            <span>{{ strtoupper($code) }}</span>
                            @if($locale === $code)
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</header>

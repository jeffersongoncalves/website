@php
    $locale = \App\Support\LocaleSupport::short();
    $locales = \App\Http\Middleware\SetLocale::SUPPORTED;
    $localeUrl = fn (string $target) => route('locale.switch', ['locale' => $target]);
    $active = fn (string ...$patterns): bool => request()->routeIs(...$patterns);
@endphp

<header class="site-header" x-data="stickyHeader()" :class="{ 'scrolled': scrolled }">
    <div class="wrap site-header-inner">
        <a href="{{ route('home') }}" class="site-brand" aria-label="Jefferson Gonçalves — Home">
            <img src="{{ Vite::asset('resources/images/icon-32.png') }}"
                 srcset="{{ Vite::asset('resources/images/icon-32.png') }} 1x, {{ Vite::asset('resources/images/icon-64.png') }} 2x"
                 alt="" width="22" height="22" class="jg-mark-img">
            <span>Jefferson Gonçalves</span>
        </a>

        <nav class="hidden md:flex items-center gap-8 text-[0.9375rem]" aria-label="@lang('site.common.nav_primary')">
            <a href="{{ route('about') }}" @class(['nav-link', 'is-active' => $active('about')]) @if($active('about')) aria-current="page" @endif>@lang('site.nav.about')</a>
            <a href="{{ route('projects.index') }}" @class(['nav-link', 'is-active' => $active('projects.*')]) @if($active('projects.*')) aria-current="page" @endif>@lang('site.nav.projects')</a>
            <a href="{{ route('articles.index') }}" @class(['nav-link', 'is-active' => $active('articles.*')]) @if($active('articles.*')) aria-current="page" @endif>@lang('site.nav.articles')</a>
            <a href="{{ route('open-source') }}" @class(['nav-link', 'is-active' => $active('open-source')]) @if($active('open-source')) aria-current="page" @endif>@lang('site.nav.open_source')</a>
            <a href="{{ route('stack') }}" @class(['nav-link', 'is-active' => $active('stack')]) @if($active('stack')) aria-current="page" @endif>@lang('site.nav.stack')</a>
            <a href="{{ route('sponsors') }}" @class(['nav-link', 'is-active' => $active('sponsors')]) @if($active('sponsors')) aria-current="page" @endif>@lang('site.nav.sponsors')</a>
            {{-- Demos: live demo subdomains, one per starter kit. Dropdown
                 reuses `lang-dropdown-menu` styling (same anchored panel
                 pattern as the locale switcher) so no new CSS is required. --}}
            <div class="lang-dropdown" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                <button type="button"
                        class="nav-link inline-flex items-center gap-1"
                        @click="open = !open"
                        :aria-expanded="open.toString()"
                        aria-haspopup="true">
                    <span>@lang('site.nav.demos')</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" :class="{ 'rotate-180': open }" style="transition:transform 200ms var(--ease-out);"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <ul class="lang-dropdown-menu"
                    role="menu"
                    x-show="open"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    x-cloak>
                    @foreach(config('site.demos', []) as $demo)
                        <li role="none">
                            <a href="{{ $demo['url'] }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="lang-dropdown-item">
                                <span>{{ $demo['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </nav>

        <div class="flex items-center gap-2">
            {{-- PWA install entry point. Renders a tight icon-only button when
                 Chrome/Edge fires `beforeinstallprompt` OR when the visitor
                 is on iOS Safari (no event, but eligible for manual install
                 via Share → Add to Home Screen). Both paths are gated by the
                 `installPrompt()` Alpine component. --}}
            <div x-data="installPrompt()" x-cloak class="flex items-center">
                <button type="button"
                        x-show="available"
                        x-on:click="install()"
                        class="pwa-install-btn"
                        :aria-label="@js(__('site.pwa.install'))"
                        :title="@js(__('site.pwa.install'))">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                </button>

                <button type="button"
                        x-show="iosEligible && !available"
                        x-on:click="showIosHint()"
                        class="pwa-install-btn"
                        :aria-label="@js(__('site.pwa.install'))"
                        :title="@js(__('site.pwa.install'))">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
                        <polyline points="16 6 12 2 8 6"/>
                        <line x1="12" y1="2" x2="12" y2="15"/>
                    </svg>
                </button>

                {{-- iOS Add to Home Screen tutorial. Standard Safari modal
                     pattern: backdrop scrim, centred card, dismiss on
                     overlay click or Escape. --}}
                <template x-teleport="body">
                    <div x-show="iosHintOpen"
                         x-cloak
                         x-on:keydown.escape.window="closeIosHint()"
                         class="pwa-ios-hint-overlay"
                         x-transition.opacity>
                        <div class="pwa-ios-hint-card"
                             x-on:click.outside="closeIosHint()"
                             x-transition>
                            <h2 class="pwa-ios-hint-title">@lang('site.pwa.ios.title')</h2>
                            <ol class="pwa-ios-hint-steps">
                                <li>@lang('site.pwa.ios.step_1')</li>
                                <li>@lang('site.pwa.ios.step_2')</li>
                                <li>@lang('site.pwa.ios.step_3')</li>
                            </ol>
                            <button type="button"
                                    x-on:click="closeIosHint()"
                                    class="pwa-ios-hint-close">
                                @lang('site.pwa.ios.close')
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <button type="button"
                    class="theme-toggle"
                    x-data="{ isDark: document.documentElement.classList.contains('dark') }"
                    @click="
                        isDark = !isDark;
                        const h = document.documentElement;
                        const bg = isDark ? '#0B0A09' : '#FFFEF9';
                        h.classList.toggle('dark', isDark);
                        h.style.colorScheme = isDark ? 'dark' : 'light';
                        h.style.background = bg;
                        const tcm = document.getElementById('theme-color-meta');
                        if (tcm) tcm.content = bg;
                        document.cookie = 'theme=' + (isDark ? 'dark' : 'light') + '; max-age=31536000; path=/; SameSite=Lax';
                    "
                    :aria-label="isDark ? @js(__('site.common.toggle_light')) : @js(__('site.common.toggle_dark'))"
                    :title="isDark ? @js(__('site.common.toggle_light')) : @js(__('site.common.toggle_dark'))">
                <svg x-show="isDark" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                <svg x-show="!isDark" x-cloak xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>

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

            <div class="mobile-nav md:hidden" x-data="{ open: false }"
                 @click.outside="open = false"
                 @keydown.escape.window="open = false">
                <button type="button"
                        class="mobile-nav-toggle"
                        @click="open = !open"
                        :aria-expanded="open.toString()"
                        :aria-label="open ? @js(__('site.common.close_menu')) : @js(__('site.common.open_menu'))">
                    <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    <svg x-show="open" x-cloak xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
                <nav class="mobile-nav-panel"
                     aria-label="@lang('site.common.nav_mobile')"
                     x-show="open"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     x-cloak>
                    <a href="{{ route('about') }}" @class(['mobile-nav-link', 'is-active' => $active('about')]) @if($active('about')) aria-current="page" @endif>@lang('site.nav.about')</a>
                    <a href="{{ route('projects.index') }}" @class(['mobile-nav-link', 'is-active' => $active('projects.*')]) @if($active('projects.*')) aria-current="page" @endif>@lang('site.nav.projects')</a>
                    <a href="{{ route('articles.index') }}" @class(['mobile-nav-link', 'is-active' => $active('articles.*')]) @if($active('articles.*')) aria-current="page" @endif>@lang('site.nav.articles')</a>
                    <a href="{{ route('open-source') }}" @class(['mobile-nav-link', 'is-active' => $active('open-source')]) @if($active('open-source')) aria-current="page" @endif>@lang('site.nav.open_source')</a>
                    <a href="{{ route('stack') }}" @class(['mobile-nav-link', 'is-active' => $active('stack')]) @if($active('stack')) aria-current="page" @endif>@lang('site.nav.stack')</a>
                    <a href="{{ route('sponsors') }}" @class(['mobile-nav-link', 'is-active' => $active('sponsors')]) @if($active('sponsors')) aria-current="page" @endif>@lang('site.nav.sponsors')</a>
                    {{-- Demos rendered inline (not a nested disclosure) — mobile
                         already collapses the nav, so a second level of
                         expand/collapse adds taps for no real gain. --}}
                    <div class="mobile-nav-link" aria-hidden="true">@lang('site.nav.demos')</div>
                    @foreach(config('site.demos', []) as $demo)
                        <a href="{{ $demo['url'] }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="mobile-nav-link pl-6">{{ $demo['label'] }}</a>
                    @endforeach
                </nav>
            </div>
        </div>
    </div>
</header>

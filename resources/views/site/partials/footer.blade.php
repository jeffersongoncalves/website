@php $locale = \App\Support\LocaleSupport::short(); @endphp

<footer class="site-footer">
    <div class="wrap site-footer-inner">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-16">
            <div>
                <div class="site-brand">
                    <img src="{{ Vite::asset('resources/images/icon-32.png') }}"
                         srcset="{{ Vite::asset('resources/images/icon-32.png') }} 1x, {{ Vite::asset('resources/images/icon-64.png') }} 2x"
                         alt="" width="22" height="22" class="jg-mark-img">
                    <span>Jefferson Gonçalves</span>
                </div>
                <p class="mt-4 body-sm max-w-[32ch]">@lang('site.footer.tagline')</p>
            </div>

            <div>
                <div class="site-footer-title">@lang('site.footer.navigation')</div>
                <ul class="site-footer-list">
                    <li><a href="{{ route('about') }}">@lang('site.nav.about')</a></li>
                    <li><a href="{{ route('projects.index') }}">@lang('site.nav.projects')</a></li>
                    <li><a href="{{ route('open-source') }}">@lang('site.nav.open_source')</a></li>
                    <li><a href="{{ route('sponsors') }}">@lang('site.nav.sponsors')</a></li>
                </ul>
            </div>

            <div>
                <div class="site-footer-title">@lang('site.footer.social')</div>
                <ul class="site-footer-list">
                    <li><a href="{{ config('site.social.github') }}"    rel="noopener" target="_blank">GitHub ↗</a></li>
                    <li><a href="{{ config('site.social.linkedin') }}"  rel="noopener" target="_blank">LinkedIn ↗</a></li>
                    <li><a href="{{ config('site.social.packagist') }}" rel="noopener" target="_blank">Packagist ↗</a></li>
                    <li><a href="{{ config('site.social.x') }}"         rel="noopener" target="_blank">X (Twitter) ↗</a></li>
                </ul>
            </div>
        </div>

        <div class="site-footer-bottom">
            <span>© {{ date('Y') }} Jefferson Gonçalves · @lang('site.footer.copyright')</span>
            <span>
                @lang('site.footer.location')
            </span>
        </div>
    </div>
</footer>

<div>
    <section class="section section-first">
        <div class="wrap">
            <x-site.eyebrow num="01" :label="__('site.nav.sponsors')"/>
            <h1>
                @lang('site.sponsors.title_1')<br>
                <span class="h-sub">@lang('site.sponsors.title_2')</span>
            </h1>
            <p class="lede mt-6">@lang('site.sponsors.sub')</p>
        </div>
    </section>

    <div class="divider"></div>

    <section class="section">
        <div class="wrap">
            <x-site.eyebrow num="02" label="canal"/>

            <a href="{{ \App\Support\OutboundLink::to(config('site.social.sponsors'), 'GitHub Sponsors') }}" rel="noopener" target="_blank"
               class="card card-pad-lg flex items-center justify-between">
                <div class="flex items-center gap-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="currentColor" class="text-amber"><path d="M12 .5a12 12 0 0 0-3.79 23.4c.6.1.82-.26.82-.58v-2.05c-3.34.73-4.04-1.6-4.04-1.6-.55-1.4-1.34-1.77-1.34-1.77-1.1-.75.08-.74.08-.74 1.21.09 1.85 1.24 1.85 1.24 1.08 1.84 2.83 1.31 3.52 1 .11-.78.42-1.31.77-1.62-2.66-.3-5.46-1.33-5.46-5.93 0-1.31.47-2.38 1.24-3.22-.12-.3-.54-1.52.12-3.18 0 0 1.01-.32 3.3 1.23a11.5 11.5 0 0 1 6 0c2.29-1.55 3.3-1.23 3.3-1.23.66 1.66.24 2.88.12 3.18.77.84 1.24 1.91 1.24 3.22 0 4.61-2.81 5.62-5.48 5.92.43.37.81 1.1.81 2.22v3.29c0 .32.22.69.83.57A12 12 0 0 0 12 .5"/></svg>
                    <div>
                        <div class="font-medium text-[1.25rem] text-ink-100 font-[Fraunces]">@lang('site.sponsors.github_sponsors')</div>
                        <div class="mt-1 mono-meta-sm">@lang('site.sponsors.github_sub')</div>
                    </div>
                </div>
                <span class="mono text-base text-ink-400">→</span>
            </a>
        </div>
    </section>
</div>

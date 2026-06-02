<div>
    <section class="section section-first">
        <div class="wrap">
            <x-site.eyebrow num="01" :label="__('site.nav.projects')"/>
            <h1>@lang('site.projects.title')</h1>
            <p class="lede mt-6">@lang('site.projects.sub')</p>

            @php
                $countItems = [
                    ['v' => $counts['total'],         'label' => __('site.os.repos')],
                    ['v' => $counts['catalogue'],     'label' => __('site.os.catalogue')],
                    ['v' => $counts['filament'],      'label' => __('site.os.plugins_filament')],
                    ['v' => $counts['laravel'],       'label' => __('site.os.packages_laravel')],
                    ['v' => $counts['starter'],       'label' => __('site.os.starter_kits')],
                    ['v' => $counts['maintained'],    'label' => __('site.os.maintained')],
                    ['v' => $counts['daily_drivers'], 'label' => __('site.os.daily_drivers')],
                ];
            @endphp
            <div class="flex flex-wrap gap-8 mt-8 mono-meta-sm">
                @foreach($countItems as $c)
                    <span><span class="text-ink-100">{{ $c['v'] }}</span> {{ $c['label'] }}</span>
                @endforeach
            </div>
        </div>
    </section>

    <div class="divider"></div>

    <section class="section" id="catalogue" style="scroll-margin-top: 6rem;">
        <div class="wrap">
            <x-site.eyebrow num="02" :label="__('site.projects.eyebrow_catalogue')"/>
            <livewire:site.projects-list/>
        </div>
    </section>

    <div class="divider"></div>

    <section class="section">
        <div class="wrap">
            <x-site.eyebrow num="03" :label="__('site.projects.eyebrow_contribute')"/>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-end">
                <div class="lg:col-span-7">
                    <h2 class="h-section">@lang('site.projects.cta_title')</h2>
                    <p class="body-text mt-6 max-w-[60ch]">
                        {!! __('site.projects.cta_body', ['code' => '<code class="inline">' . __('site.projects.good_first_issue') . '</code>']) !!}
                    </p>
                </div>
                <div class="lg:col-span-5 flex flex-wrap gap-3 lg:justify-end">
                    <a href="{{ config('site.social.github') }}" rel="noopener" target="_blank" class="btn btn-primary">
                        @lang('site.common.view_github')
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
                    </a>
                    <a href="{{ route('sponsors') }}" class="btn btn-secondary">@lang('site.common.sponsor_btn')</a>
                </div>
            </div>
        </div>
    </section>
</div>

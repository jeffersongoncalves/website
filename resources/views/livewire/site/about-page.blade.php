@php $locale = \App\Support\LocaleSupport::short(); @endphp

<div>
    <section class="section section-first">
        <div class="wrap">
            <x-site.eyebrow num="01" :label="__('site.nav.about')"/>
            <h1>
                @lang('site.about.title_1')<br>
                <span class="h-sub">@lang('site.about.title_2')</span>
            </h1>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 mt-12">
                <div class="lg:col-span-7">
                    <p class="lede">@lang('site.about.intro')</p>
                    <p class="body-text mt-6">@lang('site.about.intro_2')</p>
                </div>
                <div class="lg:col-span-5">
                    <div class="card">
                        <div class="mono-tag mb-4">@lang('site.about.metadata')</div>
                        <dl class="flex flex-col gap-3 mono text-[0.875rem] text-ink-100">
                            @foreach ([
                                ['label' => __('site.about.location'),     'value' => 'Assis, SP — BR'],
                                ['label' => __('site.about.timezone'),     'value' => 'BRT (UTC-3)'],
                                ['label' => __('site.about.experience'),   'value' => __('site.about.experience_v')],
                                ['label' => __('site.about.main_stack'),   'value' => 'Laravel · Filament'],
                                ['label' => __('site.about.github_label'), 'value' => '@jeffersongoncalves'],
                            ] as $row)
                                <div class="flex justify-between">
                                    <dt class="text-ink-500">{{ $row['label'] }}</dt>
                                    <dd>{{ $row['value'] }}</dd>
                                </div>
                            @endforeach
                            <div class="flex justify-between">
                                <dt class="text-ink-500">@lang('site.about.availability')</dt>
                                <dd class="text-success">@lang('site.about.open')</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="divider"></div>

    <section class="section">
        <div class="wrap">
            <x-site.eyebrow num="02" :label="__('site.about.eyebrow_timeline')"/>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
                <div class="lg:col-span-4">
                    <h2 class="h-section">
                        @lang('site.about.timeline_title')<br>
                        @lang('site.about.timeline_title_2')
                    </h2>
                    <p class="body-sm mt-6">@lang('site.about.timeline_sub')</p>
                </div>

                <div class="lg:col-span-8 flex flex-col gap-12">
                    @foreach($timeline as $entry)
                        <div class="tl-row">
                            <span class="tl-year {{ ($entry['accent'] ?? false) ? 'tl-year-accent' : '' }}">
                                {{ $entry['year_short'] }}
                            </span>
                            <div>
                                <h3 class="tl-title">{{ $entry['title_' . $locale] ?? $entry['title_pt'] }}</h3>
                                <div class="tl-period">{{ $entry['period_' . $locale] ?? $entry['period_pt'] }}</div>
                                <p class="tl-desc">{{ $entry['desc_' . $locale] ?? $entry['desc_pt'] }}</p>
                                <div class="flex flex-wrap gap-2 mt-3">
                                    @foreach($entry['stack'] as $s)
                                        <span class="badge">{{ $s }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-12 pt-12 border-t border-ink-800">
                <div class="mono-tag mb-4">@lang('site.about.education')</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($education as $edu)
                        <div class="card">
                            <div class="mono text-[0.75rem] text-amber">{{ $edu['period'] }}</div>
                            <h4 class="mt-2">{{ $edu['title_' . $locale] ?? $edu['title_pt'] }}</h4>
                            <p class="mt-1 body-xs">{{ $edu['school'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <div class="divider"></div>

    <section class="section">
        <div class="wrap">
            <x-site.eyebrow num="03" :label="__('site.about.eyebrow_principles')"/>
            <h2 class="h-section mb-12">@lang('site.about.principles_title')</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach($principles as $i => $p)
                    <div class="card">
                        <div class="mono text-[0.8125rem] text-amber">{{ str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) }}</div>
                        <h3 class="h-card mt-3">{{ $p['title_' . $locale] ?? $p['title_pt'] }}</h3>
                        <p class="body-sm mt-3">{{ $p['desc_' . $locale] ?? $p['desc_pt'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <div class="divider"></div>

    <section class="section">
        <div class="wrap">
            <x-site.eyebrow num="04" :label="__('site.nav.projects')"/>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-end">
                <div class="lg:col-span-7">
                    <h2 class="h-section">@lang('site.about.cta_title')</h2>
                    <p class="body-text mt-6 max-w-[60ch]">@lang('site.about.cta_body')</p>
                </div>
                <div class="lg:col-span-5 flex flex-wrap gap-3 lg:justify-end">
                    <a href="{{ route('projects.index') }}" class="btn btn-primary">@lang('site.common.view_projects')</a>
                    <a href="{{ config('site.social.github') }}" rel="noopener" target="_blank" class="btn btn-secondary">@lang('site.common.view_github')</a>
                </div>
            </div>
        </div>
    </section>
</div>

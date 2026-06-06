@php
    $locale = \App\Support\LocaleSupport::short();
    $statsJson = collect($homeStats)->map(fn ($s) => array_merge($s, ['label' => __('site.' . $s['label_key'])]))->values()->toJson();

    $personLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Person',
        'name' => 'Jefferson Gonçalves',
        'url' => route('home'),
        'jobTitle' => __('site.home.hero_l1'),
        'inLanguage' => str_replace('_', '-', app()->getLocale()),
        'sameAs' => [
            'https://github.com/jeffersongoncalves',
            'https://www.linkedin.com/in/jeffersonsimaogoncalves/',
            'https://x.com/gersonsimao92',
        ],
    ];
@endphp

@push('head')
    <x-site.json-ld :data="$personLd"/>
@endpush

<div>
    <section class="section section-first" id="hero">
        <div class="wrap">
            <x-site.eyebrow num="01" :label="__('site.nav.about')"/>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
                <div class="lg:col-span-8">
                    <div class="pill">
                        <span class="pulse-dot"></span>
                        <span>@lang('site.home.badge_available')</span>
                    </div>

                    <h1 class="h-hero mt-6">
                        @lang('site.home.hero_l1')<br>
                        <em class="hero-em">@lang('site.home.hero_l2')</em>
                    </h1>

                    <p class="lede mt-6">@lang('site.home.hero_sub')</p>

                    <div class="flex flex-wrap gap-3 mt-8">
                        <a href="{{ route('projects.index') }}" class="btn btn-primary">
                            @lang('site.common.view_projects')
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </a>
                        <a href="{{ route('sponsors') }}" class="btn btn-secondary">@lang('site.common.sponsor_btn')</a>
                    </div>

                    <div class="meta-strip">
                        <span class="pulse-dot pulse-dot-amber"></span>
                        @lang('site.common.location')
                    </div>
                </div>

                <div class="lg:col-span-4"
                     x-data="terminalTyping({ target: 'jeffersongoncalves/filament-mail-editor', delay: 600, speed: 60 })">
                    <div class="terminal">
                        <div class="terminal-bar">
                            <div class="terminal-dots">
                                <span class="terminal-dot"></span>
                                <span class="terminal-dot"></span>
                                <span class="terminal-dot"></span>
                            </div>
                            <span class="mono-meta">~/projects · zsh</span>
                        </div>
                        <div class="terminal-body">
                            <div class="term-line"><span class="text-amber">➜</span> <span class="text-ink-100">composer require</span> <span x-text="typed"></span><span class="term-cursor" x-show="!typingDone"></span></div>
                            <template x-if="typingDone">
                                <div>
                                    <div class="term-line mt-2 text-ink-500">// → Resolving dependencies</div>
                                    <div class="term-line text-ink-500">// → Generating optimized autoload</div>
                                    <div class="term-line mt-1"><span class="text-success">✓</span> Plugin installed in 1.4s</div>
                                    <div class="term-line mt-3"><span class="text-amber">➜</span> <span class="text-ink-400">_</span></div>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="mt-3 mono-meta">// 20+ plugins · packagist.org/users/jeffersongoncalves</div>
                </div>
            </div>
        </div>
    </section>

    <section class="section divider" id="how">
        <div class="wrap">
            <x-site.eyebrow num="02" label="stack"/>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
                <div class="lg:col-span-5">
                    <h2 class="h-section">@lang('site.home.how_title')</h2>
                </div>
                <div class="lg:col-span-7">
                    <p class="body-text">@lang('site.home.how_body')</p>
                </div>
            </div>

            <div class="grid grid-cols-4 lg:grid-cols-8 gap-3 mt-12">
                @foreach($stack as $tile)
                    <div class="stack-tile">
                        <span>{{ $tile['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section divider" id="open-source" x-data='countUp({{ $statsJson }})'>
        <div class="wrap">
            <x-site.eyebrow num="03" label="open source"/>

            <div class="flex items-end justify-between flex-wrap gap-4 mb-10">
                <h2 class="h-section">@lang('site.home.os_title')</h2>
                <a href="{{ config('site.social.github') }}" rel="noopener" target="_blank"
                   class="btn-ghost mono text-[0.875rem] text-ink-400">github.com/jeffersongoncalves ↗</a>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <template x-for="s in stats" :key="s.label">
                    <div class="card stat-card">
                        <svg class="stat-arrow" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/>
                        </svg>
                        <div class="stat-num" x-text="s.shown"></div>
                        <div class="stat-label" x-text="s.label"></div>
                    </div>
                </template>
            </div>

            {{-- Heatmap depends on the GitHub API — hidden until contribution data is available. --}}
            @if (! empty($contributions['cells']))
            <div class="mt-12">
                <div class="flex items-center justify-between flex-wrap gap-2 mb-4">
                    <div class="mono-meta-sm text-ink-400">
                        @lang('site.home.heatmap_title')
                        <span class="text-ink-100">· {{ number_format($contributions['total'], 0, ',', '.') }}</span>
                    </div>
                    <div class="flex items-center gap-2 mono-meta">
                        <span>@lang('site.common.less')</span>
                        <span class="hm-cell"></span>
                        <span class="hm-cell" data-level="1"></span>
                        <span class="hm-cell" data-level="2"></span>
                        <span class="hm-cell" data-level="3"></span>
                        <span class="hm-cell" data-level="4"></span>
                        <span>@lang('site.common.more')</span>
                    </div>
                </div>
                <div class="overflow-x-auto no-scrollbar">
                    {{-- Server-rendered (no Alpine x-for): the level → colour map
                         lives in CSS via data-level, identical to the old bgFor().
                         Lands in the page cache as static HTML. --}}
                    <div class="grid grid-rows-7 grid-flow-col gap-[3px] w-max">
                        @foreach($contributions['cells'] as $c)
                            <span class="hm-cell"@if($c > 0) data-level="{{ $c }}"@endif></span>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>
    </section>

    <section class="section divider" id="projetos">
        <div class="wrap">
            <x-site.eyebrow num="04" :label="__('site.nav.projects')"/>

            <div class="flex items-end justify-between flex-wrap gap-4 mb-8">
                <div>
                    <h2 class="h-section">@lang('site.home.projects_title')</h2>
                    <p class="mt-3 body-sm max-w-[50ch]">@lang('site.home.projects_sub')</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach($featured as $project)
                    <x-site.project-card :project="$project"/>
                @endforeach
            </div>

            <div class="flex justify-center mt-12">
                <a href="{{ route('projects.index') }}" class="btn-ghost mono text-[0.9375rem] text-ink-200">
                    @lang('site.home.view_all')
                </a>
            </div>
        </div>
    </section>

    <section class="section divider" id="sponsors">
        <div class="wrap">
            <x-site.eyebrow num="05" :label="__('site.nav.sponsors')"/>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">
                <div class="lg:col-span-6">
                    <h2 class="h-section">@lang('site.home.sponsors_title')</h2>
                    <p class="mt-6 body-text">@lang('site.home.sponsors_body')</p>
                    <p class="mt-4 body-text">@lang('site.home.sponsors_extra')</p>
                </div>

                <div class="lg:col-span-6">
                    <a href="{{ config('site.social.sponsors') }}" rel="noopener" target="_blank"
                       class="card flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="text-amber"><path d="M12 .5a12 12 0 0 0-3.79 23.4c.6.1.82-.26.82-.58v-2.05c-3.34.73-4.04-1.6-4.04-1.6-.55-1.4-1.34-1.77-1.34-1.77-1.1-.75.08-.74.08-.74 1.21.09 1.85 1.24 1.85 1.24 1.08 1.84 2.83 1.31 3.52 1 .11-.78.42-1.31.77-1.62-2.66-.3-5.46-1.33-5.46-5.93 0-1.31.47-2.38 1.24-3.22-.12-.3-.54-1.52.12-3.18 0 0 1.01-.32 3.3 1.23a11.5 11.5 0 0 1 6 0c2.29-1.55 3.3-1.23 3.3-1.23.66 1.66.24 2.88.12 3.18.77.84 1.24 1.91 1.24 3.22 0 4.61-2.81 5.62-5.48 5.92.43.37.81 1.1.81 2.22v3.29c0 .32.22.69.83.57A12 12 0 0 0 12 .5"/></svg>
                            <div>
                                <div class="font-medium text-[1.0625rem] text-ink-100">@lang('site.sponsors.github_sponsors')</div>
                                <div class="mt-1 mono-meta">@lang('site.sponsors.github_sub')</div>
                            </div>
                        </div>
                        <span class="mono text-[0.875rem] text-ink-400">→</span>
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>

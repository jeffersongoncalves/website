@php
    $locale = \JeffersonGoncalves\LocaleCookie\LocaleCookie::short();
    $statsJson = collect($osStats)->map(fn ($s) => array_merge($s, ['label' => __('site.' . $s['label_key'])]))->values()->toJson();
@endphp

<div>
    <section class="section section-first">
        <div class="wrap">
            <x-site.eyebrow num="01" label="open source"/>
            <h1>
                @lang('site.os.page_title')<br>
                <span class="h-sub">@lang('site.os.page_title_2')</span>
            </h1>
            <p class="lede mt-6">@lang('site.os.page_sub')</p>
        </div>
    </section>

    <div class="divider"></div>

    <section class="section" x-data='countUp({{ $statsJson }})'>
        <div class="wrap">
            <x-site.eyebrow num="02" label="métricas"/>
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
        </div>
    </section>

    {{-- Heatmap depends on the GitHub API — section hidden until contribution data is available. --}}
    @if (! empty($contributions['cells']))
    <div class="divider"></div>

    <section class="section">
        <div class="wrap">
            <x-site.eyebrow num="03" label="contribuições"/>
            <div class="flex items-end justify-between flex-wrap gap-4 mb-6">
                <h2 class="h-section">
                    @lang('site.os.contributions')<br>
                    <span class="h-sub">{{ number_format($contributions['total'], 0, ',', '.') }} @lang('site.os.contributions_2')</span>
                </h2>
                <div class="flex items-center gap-2 mono-meta" aria-hidden="true">
                    <span>@lang('site.common.less')</span>
                    <span class="hm-cell"></span>
                    <span class="hm-cell" data-level="1"></span>
                    <span class="hm-cell" data-level="2"></span>
                    <span class="hm-cell" data-level="3"></span>
                    <span class="hm-cell" data-level="4"></span>
                    <span>@lang('site.common.more')</span>
                </div>
            </div>
            {{-- The per-day cells encode count by colour only, so they're hidden
                 from assistive tech; the grid as a whole carries one descriptive
                 label with the yearly total instead. --}}
            <div class="overflow-x-auto no-scrollbar">
                {{-- Server-rendered (no Alpine x-for): the level → colour map
                     lives in CSS via data-level. Identical to the home heatmap;
                     lands in the page cache as static HTML. --}}
                <div class="grid grid-rows-7 grid-flow-col gap-[3px] w-max"
                     role="img"
                     aria-label="{{ number_format($contributions['total'], 0, ',', '.') }} @lang('site.os.contributions_2')">
                    @foreach($contributions['cells'] as $c)
                        <span class="hm-cell"@if($c > 0) data-level="{{ $c }}"@endif aria-hidden="true"></span>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
    @endif

    <div class="divider"></div>

    <section class="section">
        <div class="wrap">
            <x-site.eyebrow num="04" label="top repos"/>
            <h2 class="h-section mb-12">@lang('site.os.top_repos')</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach($topRepos as $project)
                    <x-site.project-card :project="$project"/>
                @endforeach
            </div>
            <div class="flex justify-center mt-12">
                <a href="{{ route('projects.index') }}" class="btn-ghost mono text-[0.9375rem] text-ink-200">
                    {{ __('site.os.view_all_repos', ['count' => $reposCount]) }}
                </a>
            </div>
        </div>
    </section>

    <div class="divider"></div>

    <section class="section">
        <div class="wrap">
            <x-site.eyebrow num="05" :label="__('site.nav.sponsors')"/>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-end">
                <div class="lg:col-span-7">
                    <h2 class="h-section">
                        @lang('site.os.sponsors_count')<br>
                        <span class="h-sub">@lang('site.os.sponsors_count_2')</span>
                    </h2>
                    <p class="body-text mt-6 max-w-[60ch]">@lang('site.home.sponsors_body')</p>
                </div>
                <div class="lg:col-span-5 flex flex-wrap gap-3 lg:justify-end">
                    <a href="{{ route('sponsors') }}" class="btn btn-primary">Ver tiers →</a>
                    <a href="{{ \App\Support\OutboundLink::to(config('site.social.sponsors'), 'GitHub Sponsors') }}" rel="noopener" target="_blank" class="btn btn-secondary">GitHub Sponsors ↗</a>
                </div>
            </div>
        </div>
    </section>
</div>

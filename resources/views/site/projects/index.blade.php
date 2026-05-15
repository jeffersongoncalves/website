@php $locale = app()->getLocale(); @endphp

<x-site.layouts.app :title="__('site.projects.title')" :description="__('site.seo.projects')">

    <section class="section section-first">
        <div class="wrap">
            <x-site.eyebrow num="01" :label="__('site.nav.projects')"/>
            <h1>@lang('site.projects.title')</h1>
            <p class="lede mt-6">@lang('site.projects.sub')</p>

            @php
                $countItems = [
                    ['v' => $counts['total'],      'label' => __('site.os.repos')],
                    ['v' => $counts['filament'],   'label' => __('site.os.plugins_filament')],
                    ['v' => $counts['laravel'],    'label' => __('site.os.packages_laravel')],
                    ['v' => $counts['starter'],    'label' => __('site.os.starter_kits')],
                    ['v' => $counts['maintained'], 'label' => __('site.os.maintained')],
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

    <section class="section">
        <div class="wrap">
            <x-site.eyebrow num="02" label="catálogo"/>

            @php
                $catParam = $activeCat === 'all' ? null : $activeCat;
                $roleParam = $activeRole === 'all' ? null : $activeRole;
                $catLink = fn ($c) => route('projects.index', array_filter(['locale' => $locale, 'cat' => $c, 'role' => $roleParam, 'sort' => $activeSort === 'stars' ? null : $activeSort]));
                $roleLink = fn ($r) => route('projects.index', array_filter(['locale' => $locale, 'cat' => $catParam, 'role' => $r, 'sort' => $activeSort === 'stars' ? null : $activeSort]));
                $sortLink = fn ($s) => route('projects.index', array_filter(['locale' => $locale, 'cat' => $catParam, 'role' => $roleParam, 'sort' => $s]));
            @endphp

            <div class="flex flex-wrap items-center gap-3 mb-8">
                <div class="overflow-x-auto no-scrollbar flex-1">
                    <div class="flex gap-2 min-w-max">
                        <a href="{{ $catLink(null) }}"
                           class="chip {{ $activeCat === 'all' ? 'chip-active' : '' }}">
                            @lang('site.projects.category_all')
                        </a>
                        @foreach($categories as $cat)
                            <a href="{{ $catLink($cat->value) }}"
                               class="chip {{ $activeCat === $cat->value ? 'chip-active' : '' }}">
                                {{ $cat->getLabel() }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center gap-2 mono-meta">
                    <span>@lang('site.common.sort_by')</span>
                    <a href="{{ $sortLink('stars') }}"     class="{{ $activeSort === 'stars' ? 'text-ink-100' : 'text-ink-500' }}">@lang('site.common.sort_stars')</a>
                    <span>·</span>
                    <a href="{{ $sortLink('downloads') }}" class="{{ $activeSort === 'downloads' ? 'text-ink-100' : 'text-ink-500' }}">@lang('site.common.sort_downloads')</a>
                    <span>·</span>
                    <a href="{{ $sortLink('name') }}"      class="{{ $activeSort === 'name' ? 'text-ink-100' : 'text-ink-500' }}">@lang('site.common.sort_az')</a>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 mb-8">
                <a href="{{ $roleLink(null) }}"
                   class="chip {{ $activeRole === 'all' ? 'chip-active' : '' }}">
                    @lang('site.projects.role_all')
                </a>
                <a href="{{ $roleLink('authored') }}"
                   class="chip {{ $activeRole === 'authored' ? 'chip-active' : '' }}">
                    @lang('site.projects.role_authored')
                </a>
                <a href="{{ $roleLink('maintainer') }}"
                   class="chip {{ $activeRole === 'maintainer' ? 'chip-active' : '' }}">
                    @lang('site.projects.role_maintainer')
                </a>
            </div>

            @if($projects->isEmpty())
                <div class="text-center py-16 mono text-sm text-ink-500">@lang('site.common.no_results')</div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($projects as $project)
                        <x-site.project-card :project="$project"/>
                    @endforeach
                </div>

                <div class="mt-10">
                    {{ $projects->onEachSide(1)->links() }}
                </div>
            @endif
        </div>
    </section>

    <div class="divider"></div>

    <section class="section">
        <div class="wrap">
            <x-site.eyebrow num="03" label="contribuir"/>
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
                    <a href="{{ route('sponsors', ['locale' => $locale]) }}" class="btn btn-secondary">@lang('site.common.sponsor_btn')</a>
                </div>
            </div>
        </div>
    </section>

</x-site.layouts.app>

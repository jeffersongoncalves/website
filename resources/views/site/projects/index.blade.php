@php $locale = \App\Support\LocaleSupport::short(); @endphp

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

            <form method="GET" action="{{ route('projects.index') }}" class="projects-filters">
                <div class="projects-filters-row">
                    <label class="projects-filter">
                        <span class="projects-filter-label">@lang('site.projects.filter_search')</span>
                        <input type="text"
                               name="search"
                               value="{{ $activeSearch }}"
                               placeholder="{{ __('site.projects.filter_search_placeholder') }}"
                               class="projects-filter-input"
                               autocomplete="off">
                    </label>

                    <label class="projects-filter">
                        <span class="projects-filter-label">@lang('site.projects.filter_category')</span>
                        <select name="cat" class="projects-filter-select" onchange="this.form.submit()">
                            <option value="">@lang('site.projects.category_all')</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->value }}" @selected($activeCat === $cat->value)>
                                    {{ $cat->getLabel() }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="projects-filter">
                        <span class="projects-filter-label">@lang('site.projects.filter_role')</span>
                        <select name="role" class="projects-filter-select" onchange="this.form.submit()">
                            <option value="">@lang('site.projects.role_all')</option>
                            <option value="authored"   @selected($activeRole === 'authored')>@lang('site.projects.role_authored')</option>
                            <option value="maintainer" @selected($activeRole === 'maintainer')>@lang('site.projects.role_maintainer')</option>
                        </select>
                    </label>

                    <label class="projects-filter">
                        <span class="projects-filter-label">@lang('site.common.sort_by')</span>
                        <select name="sort" class="projects-filter-select" onchange="this.form.submit()">
                            <option value="stars"     @selected($activeSort === 'stars')>@lang('site.common.sort_stars')</option>
                            <option value="downloads" @selected($activeSort === 'downloads')>@lang('site.common.sort_downloads')</option>
                            <option value="name"      @selected($activeSort === 'name')>@lang('site.common.sort_az')</option>
                        </select>
                    </label>

                    <button type="submit" class="btn btn-primary projects-filter-submit">
                        @lang('site.projects.filter_apply')
                    </button>
                </div>
            </form>

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
                    <a href="{{ route('sponsors') }}" class="btn btn-secondary">@lang('site.common.sponsor_btn')</a>
                </div>
            </div>
        </div>
    </section>

</x-site.layouts.app>

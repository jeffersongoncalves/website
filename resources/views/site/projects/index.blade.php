@php $locale = \App\Support\LocaleSupport::short(); @endphp

<x-site.layouts.app :title="__('site.projects.title')" :description="__('site.seo.projects')">

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

            <form method="GET" action="{{ route('projects.index').'#catalogue' }}" class="projects-filters">
                @if($activeTopic !== '')
                    <input type="hidden" name="topic" value="{{ $activeTopic }}">
                @endif
                {{-- Role/source live as badge chips below; carry their active
                     value through the form so a search/category submit keeps them. --}}
                @if($activeRole !== 'all')
                    <input type="hidden" name="role" value="{{ $activeRole }}">
                @endif
                @if($activeSource !== 'all')
                    <input type="hidden" name="source" value="{{ $activeSource }}">
                @endif
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
                        @php
                            // Collapse the ~17 catalogue categories into their
                            // ProjectFamily <optgroup>s; cases() order keeps the
                            // families in a stable, curated sequence.
                            $catsByFamily = collect($categories)->groupBy(fn (\App\Enums\ProjectCategory $c) => $c->family()->value);
                        @endphp
                        <select name="cat" class="projects-filter-select" onchange="this.form.submit()">
                            <option value="">@lang('site.projects.category_all')</option>
                            @foreach($catsByFamily as $familyValue => $cats)
                                <optgroup label="{{ \App\Enums\ProjectFamily::from($familyValue)->getLabel() }}">
                                    @foreach($cats as $cat)
                                        <option value="{{ $cat->value }}" @selected($activeCat === $cat->value)>
                                            {{ $cat->getLabel() }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </label>

                    @if(count($languages) > 0)
                        <label class="projects-filter">
                            <span class="projects-filter-label">@lang('site.projects.filter_language')</span>
                            <select name="language" class="projects-filter-select" onchange="this.form.submit()">
                                <option value="">@lang('site.projects.language_all')</option>
                                @foreach($languages as $lang)
                                    <option value="{{ $lang }}" @selected($activeLanguage === $lang)>{{ $lang }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

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

            @php
                // Badge facets toggle a single query param while preserving the
                // other active filters; drop `page` so a new filter resets to p1.
                $roleBase = request()->except(['page', 'role']);
                $sourceBase = request()->except(['page', 'source']);
                $roleOpts = [
                    'authored' => __('site.projects.role_authored'),
                    'maintainer' => __('site.projects.role_maintainer'),
                    'daily_driver' => __('site.projects.role_daily_driver'),
                ];
                $sourceOpts = [
                    'own' => __('site.projects.source_own'),
                    'starred' => __('site.projects.source_starred'),
                ];
            @endphp
            <div class="flex flex-wrap items-center gap-2 mt-6 mono-meta-sm">
                <span class="text-ink-400">@lang('site.projects.filter_role'):</span>
                <a href="{{ route('projects.index', $roleBase).'#catalogue' }}" class="badge {{ $activeRole === 'all' ? 'badge-accent' : '' }}">@lang('site.projects.role_all')</a>
                @foreach($roleOpts as $val => $label)
                    <a href="{{ route('projects.index', array_merge($roleBase, ['role' => $val])).'#catalogue' }}"
                       class="badge {{ $activeRole === $val ? 'badge-accent' : '' }}">{{ $label }}</a>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-2 mt-3 mb-2 mono-meta-sm">
                <span class="text-ink-400">@lang('site.projects.filter_source'):</span>
                <a href="{{ route('projects.index', $sourceBase).'#catalogue' }}" class="badge {{ $activeSource === 'all' ? 'badge-accent' : '' }}">@lang('site.projects.source_all')</a>
                @foreach($sourceOpts as $val => $label)
                    <a href="{{ route('projects.index', array_merge($sourceBase, ['source' => $val])).'#catalogue' }}"
                       class="badge {{ $activeSource === $val ? 'badge-accent' : '' }}">{{ $label }}</a>
                @endforeach
            </div>

            @if(count($popularTopics) > 0)
                <div class="flex flex-wrap items-center gap-2 mt-6 mb-6 mono-meta-sm">
                    <span class="text-ink-400">@lang('site.projects.popular_topics'):</span>
                    @foreach($popularTopics as $t)
                        <a href="{{ route('projects.index', ['topic' => $t['topic']]).'#catalogue' }}"
                           class="badge {{ $activeTopic === $t['topic'] ? 'badge-accent' : '' }}">#{{ $t['topic'] }}</a>
                    @endforeach
                </div>
            @endif

            @if($activeTopic !== '')
                <div class="mt-6 mb-8 mono-meta-sm">
                    <span class="text-ink-400">@lang('site.projects.filtering_by_topic'):</span>
                    <span class="badge badge-accent ml-2">#{{ $activeTopic }}</span>
                    <a href="{{ route('projects.index', request()->except(['topic', 'page'])).'#catalogue' }}" class="ml-2 text-ink-400 hover:text-ink-200">✕ @lang('site.projects.clear_filter')</a>
                </div>
            @endif

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

</x-site.layouts.app>

<x-site.layouts.app :title="__('site.links.title')" :description="__('site.seo.links')">

    <section class="section section-first">
        <div class="wrap">
            <x-site.eyebrow num="01" :label="__('site.nav.links')"/>
            <h1>@lang('site.links.title')</h1>
            <p class="lede mt-6">@lang('site.links.sub')</p>
        </div>
    </section>

    <div class="divider"></div>

    @if(count($sections) === 0)
        <section class="section">
            <div class="wrap">
                <div class="text-center py-16 mono text-sm text-ink-500">@lang('site.common.no_results')</div>
            </div>
        </section>
    @else
        @foreach($sections as $i => $section)
            @php
                $cat = $section['category'];
                $anchor = $section['anchor'];
                $projects = $section['projects'];
                $activeTopic = $section['activeTopic'];
                // Carry every other query param through the search form so filtering
                // one section never resets another; drop this section's own search,
                // sort and page (the inputs re-supply them).
                $carry = request()->except(["q_{$anchor}", "sort_{$anchor}", $anchor]);
                // Base for the topic-chip links: keep other params, drop this
                // section's topic + page so a chip click resets the section to p1.
                $topicBase = request()->except(["topic_{$anchor}", $anchor]);
            @endphp
            <section class="section" id="{{ $anchor }}" style="scroll-margin-top: 6rem;">
                <div class="wrap">
                    <x-site.eyebrow :num="sprintf('%02d', $i + 2)" :label="__('site.links.section_'.$cat->value)"/>
                    <h2 class="h-section">{{ __('site.links.section_'.$cat->value) }}</h2>

                    {{-- Per-section search + name sort --}}
                    <form method="GET" action="{{ route('links.index').'#'.$anchor }}" class="projects-filters mt-8">
                        @foreach($carry as $k => $v)
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endforeach
                        <div class="projects-filters-row">
                            <label class="projects-filter">
                                <span class="projects-filter-label">@lang('site.projects.filter_search')</span>
                                <input type="text"
                                       name="q_{{ $anchor }}"
                                       value="{{ $section['search'] }}"
                                       placeholder="{{ __('site.links.search_placeholder') }}"
                                       class="projects-filter-input"
                                       autocomplete="off">
                            </label>

                            <label class="projects-filter">
                                <span class="projects-filter-label">@lang('site.common.sort_by')</span>
                                <select name="sort_{{ $anchor }}" class="projects-filter-select" onchange="this.form.submit()">
                                    <option value="asc"  @selected($section['dir'] === 'asc')>@lang('site.common.sort_az')</option>
                                    <option value="desc" @selected($section['dir'] === 'desc')>@lang('site.common.sort_za')</option>
                                </select>
                            </label>

                            <button type="submit" class="btn btn-primary projects-filter-submit">
                                @lang('site.projects.filter_apply')
                            </button>
                        </div>
                    </form>

                    {{-- Topic chips for this section only --}}
                    @if(count($section['topics']) > 0)
                        <div class="flex flex-wrap items-center gap-2 mt-6 mb-2 mono-meta-sm">
                            <span class="text-ink-400">@lang('site.projects.popular_topics'):</span>
                            <a href="{{ route('links.index', $topicBase).'#'.$anchor }}"
                               class="badge {{ $activeTopic === '' ? 'badge-accent' : '' }}">@lang('site.common.all')</a>
                            @foreach($section['topics'] as $t)
                                <a href="{{ route('links.index', array_merge($topicBase, ['topic_'.$anchor => $t])).'#'.$anchor }}"
                                   class="badge {{ $activeTopic === $t ? 'badge-accent' : '' }}">#{{ $t }}</a>
                            @endforeach
                        </div>
                    @endif

                    @if($activeTopic !== '')
                        <div class="mt-6 mb-8 mono-meta-sm">
                            <span class="text-ink-400">@lang('site.projects.filtering_by_topic'):</span>
                            <span class="badge badge-accent ml-2">#{{ $activeTopic }}</span>
                            <a href="{{ route('links.index', $topicBase).'#'.$anchor }}" class="ml-2 text-ink-400 hover:text-ink-200">✕ @lang('site.projects.clear_filter')</a>
                        </div>
                    @endif

                    @if($projects->isEmpty())
                        <div class="text-center py-12 mono text-sm text-ink-500">@lang('site.common.no_results')</div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
                            @foreach($projects as $project)
                                <x-site.project-card :project="$project"/>
                            @endforeach
                        </div>

                        @if($projects->hasPages())
                            <div class="mt-10">
                                {{ $projects->onEachSide(1)->links() }}
                            </div>
                        @endif
                    @endif
                </div>
            </section>
            @unless($loop->last)
                <div class="divider"></div>
            @endunless
        @endforeach
    @endif

</x-site.layouts.app>

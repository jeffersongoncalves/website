<section class="section" id="{{ $anchor }}" style="scroll-margin-top: 6rem;">
    <div class="wrap">
        <x-site.eyebrow :num="sprintf('%02d', $index + 2)" :label="__('site.links.section_'.$category->value)"/>
        <h2 class="h-section">{{ __('site.links.section_'.$category->value) }}</h2>

        {{-- Per-section search + name sort --}}
        <div class="projects-filters mt-8">
            <div class="projects-filters-row">
                <label class="projects-filter">
                    <span class="projects-filter-label">@lang('site.projects.filter_search')</span>
                    <input type="text"
                           wire:model.live.debounce.400ms="search"
                           value="{{ $search }}"
                           placeholder="{{ __('site.links.search_placeholder') }}"
                           class="projects-filter-input"
                           autocomplete="off">
                </label>

                <label class="projects-filter">
                    <span class="projects-filter-label">@lang('site.common.sort_by')</span>
                    <select wire:model.live="dir" class="projects-filter-select">
                        <option value="asc" @selected($dir === 'asc')>@lang('site.common.sort_az')</option>
                        <option value="desc" @selected($dir === 'desc')>@lang('site.common.sort_za')</option>
                    </select>
                </label>
            </div>
        </div>

        {{-- Topic chips for this section only --}}
        @if(count($topics) > 0)
            <div class="flex flex-wrap items-center gap-2 mt-6 mb-2 mono-meta-sm">
                <span class="text-ink-400">@lang('site.projects.popular_topics'):</span>
                <button type="button" wire:click="setTopic('')" class="badge {{ $activeTopic === '' ? 'badge-accent' : '' }}">@lang('site.common.all')</button>
                @foreach($topics as $t)
                    <button type="button" wire:click="setTopic('{{ $t }}')" class="badge {{ $activeTopic === $t ? 'badge-accent' : '' }}">#{{ $t }}</button>
                @endforeach
            </div>
        @endif

        @if($activeTopic !== '')
            <div class="mt-6 mb-8 mono-meta-sm">
                <span class="text-ink-400">@lang('site.projects.filtering_by_topic'):</span>
                <span class="badge badge-accent ml-2">#{{ $activeTopic }}</span>
                <button type="button" wire:click="clearTopic" class="ml-2 bg-transparent border-0 cursor-pointer rounded text-ink-400 hover:text-ink-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"><span aria-hidden="true">✕</span> @lang('site.projects.clear_filter')</button>
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

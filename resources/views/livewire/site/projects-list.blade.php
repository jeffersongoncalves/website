<div>
    <div class="projects-filters">
        <div class="projects-filters-row">
            <label class="projects-filter">
                <span class="projects-filter-label">@lang('site.projects.filter_search')</span>
                <input type="text"
                       wire:model.live.debounce.400ms="search"
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
                <select wire:model.live="cat" class="projects-filter-select">
                    <option value="">@lang('site.projects.category_all')</option>
                    @foreach($catsByFamily as $familyValue => $cats)
                        <optgroup label="{{ \App\Enums\ProjectFamily::from($familyValue)->getLabel() }}">
                            @foreach($cats as $catOption)
                                <option value="{{ $catOption->value }}">
                                    {{ $catOption->getLabel() }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </label>

            @if(count($languages) > 0)
                <label class="projects-filter">
                    <span class="projects-filter-label">@lang('site.projects.filter_language')</span>
                    <select wire:model.live="language" class="projects-filter-select">
                        <option value="">@lang('site.projects.language_all')</option>
                        @foreach($languages as $lang)
                            <option value="{{ $lang }}">{{ $lang }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label class="projects-filter">
                <span class="projects-filter-label">@lang('site.common.sort_by')</span>
                <select wire:model.live="sort" class="projects-filter-select">
                    <option value="stars">@lang('site.common.sort_stars')</option>
                    <option value="downloads">@lang('site.common.sort_downloads')</option>
                    <option value="name">@lang('site.common.sort_az')</option>
                </select>
            </label>
        </div>
    </div>

    @php
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
        <button type="button" wire:click="setRole('all')" class="badge {{ $activeRole === 'all' ? 'badge-accent' : '' }}">@lang('site.projects.role_all')</button>
        @foreach($roleOpts as $val => $label)
            <button type="button" wire:click="setRole('{{ $val }}')" class="badge {{ $activeRole === $val ? 'badge-accent' : '' }}">{{ $label }}</button>
        @endforeach
    </div>
    <div class="flex flex-wrap items-center gap-2 mt-3 mb-2 mono-meta-sm">
        <span class="text-ink-400">@lang('site.projects.filter_source'):</span>
        <button type="button" wire:click="setSource('all')" class="badge {{ $activeSource === 'all' ? 'badge-accent' : '' }}">@lang('site.projects.source_all')</button>
        @foreach($sourceOpts as $val => $label)
            <button type="button" wire:click="setSource('{{ $val }}')" class="badge {{ $activeSource === $val ? 'badge-accent' : '' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if(count($popularTopics) > 0)
        <div class="flex flex-wrap items-center gap-2 mt-6 mb-6 mono-meta-sm">
            <span class="text-ink-400">@lang('site.projects.popular_topics'):</span>
            @foreach($popularTopics as $t)
                <button type="button" wire:click="setTopic('{{ $t['topic'] }}')"
                        class="badge {{ $activeTopic === $t['topic'] ? 'badge-accent' : '' }}">#{{ $t['topic'] }}</button>
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

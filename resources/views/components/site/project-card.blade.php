@props(['project'])

@php
    $locale = \App\Support\LocaleSupport::short();
    $title = $project->getTranslation('title', $locale, false) ?: $project->getTranslation('title', 'pt', false);
    // External-link projects (no repo → no stars/license): show the host
    // instead of the stars/license meta, same as the show page.
    $isExternalSite = in_array($project->category, [
        \App\Enums\ProjectCategory::Website,
        \App\Enums\ProjectCategory::YoutubeChannel,
        \App\Enums\ProjectCategory::Article,
    ], true);
@endphp

<article class="card project-card">
    <svg class="arrow-tr" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/>
    </svg>

    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 sm:gap-4 pr-6">
        <h3 class="flex-1 min-w-0 m-0">
            <a href="{{ route('projects.show', ['slug' => $project->slug]) }}"
               class="mono text-[0.95rem] font-semibold text-ink-100 break-words">{{ $project->name }}</a>
        </h3>
        <div class="flex items-center gap-2 flex-wrap sm:justify-end">
            @if($project->isCreatedByOwner())
                <span class="badge badge-success" title="{{ __('site.projects.badge_creator_help') }}">@lang('site.projects.badge_creator')</span>
            @elseif($project->is_maintainer)
                <span class="badge badge-success" title="{{ __('Maintainer, not original author') }}">{{ __('maintainer') }}</span>
            @endif
            @if($project->is_daily_driver)
                <span class="badge badge-accent" title="{{ __('site.projects.badge_daily_driver') }}">@lang('site.projects.badge_daily_driver')</span>
            @endif
            @if($project->is_paid)
                <span class="badge badge-warning" title="{{ __('site.projects.badge_paid') }}">@lang('site.projects.badge_paid')</span>
            @endif
            @if($project->starred_at)
                <span class="badge" title="{{ __('site.projects.badge_starred_help') }}"><span aria-hidden="true">★</span> @lang('site.projects.badge_starred')</span>
            @endif
            <span class="badge">{{ $project->category->getLabel() }}</span>
        </div>
    </div>

    @if($title && $title !== $project->name)
        <p class="mt-3 body-sm">{{ $title }}</p>
    @endif

    @if(!empty($project->versions) || !empty($project->stack))
        <div class="flex flex-wrap gap-2 mt-4">
            @foreach($project->versions ?? [] as $v)
                <span class="badge badge-accent">{{ $v }}</span>
            @endforeach
            @foreach($project->stack ?? [] as $s)
                <span class="badge">{{ $s }}</span>
            @endforeach
        </div>
    @endif

    @if(!empty($project->topics))
        <div class="flex flex-wrap gap-x-3 gap-y-1 mt-3 mono-meta-sm text-ink-400">
            @foreach(array_slice($project->topics, 0, 4) as $topic)
                <a href="{{ route('projects.index', ['topic' => $topic]) }}" class="hover:text-ink-200">#{{ $topic }}</a>
            @endforeach
        </div>
    @endif

    <div class="card-foot">
        <div class="card-meta-row">
            @if($isExternalSite)
                @if($project->docs_url)
                    @php
                        // YouTube channels read better with the handle (the
                        // distinguishing tail) than the shared youtube.com
                        // host; other external sites use the hostname.
                        if ($project->category === \App\Enums\ProjectCategory::YoutubeChannel
                            && preg_match('~youtube\.com/(@?[^/?#]+)~i', (string) $project->docs_url, $m)) {
                            $extLabel = $m[1];
                        } else {
                            $extLabel = parse_url($project->docs_url, PHP_URL_HOST) ?: $project->docs_url;
                        }
                    @endphp
                    <span class="mono-meta-sm"><span aria-hidden="true">↗</span> {{ $extLabel }}</span>
                @endif
            @elseif(! $project->is_paid)
                <span aria-label="{{ $project->stars }} @lang('site.projects.label_stars')"><span aria-hidden="true">★</span> {{ $project->stars }}</span>
                @if($project->downloads_label)
                    <span aria-label="{{ $project->downloads_label }} @lang('site.projects.label_downloads')"><span aria-hidden="true">↓</span> {{ $project->downloads_label }}</span>
                @endif
                <span aria-label="@lang('site.projects.label_license'): {{ $project->license }}"><span aria-hidden="true">⎘</span> {{ $project->license }}</span>
                @if($project->is_maintainer && $project->user_contributions > 0)
                    <span aria-label="{{ number_format($project->user_contributions, 0, ',', '.') }} @lang('site.projects.label_contributions')"><span aria-hidden="true">⎇</span> {{ number_format($project->user_contributions, 0, ',', '.') }}</span>
                @endif
            @endif
        </div>
        <div class="flex items-center gap-3 text-ink-400">
            @if($project->github_url)
                <a href="{{ $project->github_url }}" aria-label="GitHub" rel="noopener" target="_blank">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.4 5.4 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/></svg>
                </a>
            @endif
            @if($project->packagist_url)
                <a href="{{ $project->packagist_url }}" aria-label="Packagist" rel="noopener" target="_blank">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                </a>
            @endif
            @if($project->docker_url)
                <a href="{{ $project->docker_url }}" aria-label="Docker" rel="noopener" target="_blank">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M13.983 11.078h2.119a.186.186 0 0 0 .186-.185V9.006a.186.186 0 0 0-.186-.186h-2.119a.185.185 0 0 0-.185.185v1.888c0 .102.083.185.185.185m-2.954-5.43h2.118a.186.186 0 0 0 .186-.186V3.574a.186.186 0 0 0-.186-.185h-2.118a.185.185 0 0 0-.185.185v1.888c0 .102.082.185.185.185m0 2.716h2.118a.187.187 0 0 0 .186-.186V6.29a.186.186 0 0 0-.186-.185h-2.118a.185.185 0 0 0-.185.185v1.887c0 .102.082.185.185.186m-2.93 0h2.12a.186.186 0 0 0 .184-.186V6.29a.185.185 0 0 0-.185-.185H8.1a.185.185 0 0 0-.185.185v1.887c0 .102.083.185.185.186m-2.964 0h2.119a.186.186 0 0 0 .185-.186V6.29a.185.185 0 0 0-.185-.185H5.136a.186.186 0 0 0-.186.185v1.887c0 .102.084.185.186.186m5.893 2.715h2.118a.186.186 0 0 0 .186-.185V9.006a.186.186 0 0 0-.186-.186h-2.118a.185.185 0 0 0-.185.185v1.888c0 .102.082.185.185.185m-2.93 0h2.12a.185.185 0 0 0 .184-.185V9.006a.185.185 0 0 0-.184-.186h-2.12a.185.185 0 0 0-.184.185v1.888c0 .102.083.185.185.185m-2.964 0h2.119a.185.185 0 0 0 .185-.185V9.006a.185.185 0 0 0-.185-.186h-2.119a.186.186 0 0 0-.186.185v1.888c0 .102.084.185.186.185m-2.92 0h2.12a.185.185 0 0 0 .184-.185V9.006a.185.185 0 0 0-.184-.186h-2.12a.185.185 0 0 0-.184.185v1.888c0 .102.082.185.185.185M23.763 9.89c-.065-.051-.672-.51-1.954-.51-.338.001-.676.03-1.01.087-.248-1.7-1.653-2.53-1.716-2.566l-.344-.199-.226.327c-.284.438-.49.922-.612 1.43-.23.97-.09 1.882.403 2.661-.595.332-1.55.413-1.744.42H.751a.751.751 0 0 0-.75.748 11.376 11.376 0 0 0 .692 4.062c.545 1.428 1.355 2.48 2.41 3.124 1.18.723 3.1 1.137 5.275 1.137a16.09 16.09 0 0 0 2.93-.266 12.099 12.099 0 0 0 3.823-1.389c.98-.567 1.86-1.288 2.61-2.136 1.252-1.418 1.998-2.997 2.553-4.4h.221c1.372 0 2.215-.549 2.68-1.009.309-.293.55-.65.707-1.046l.098-.288Z"/></svg>
                </a>
            @endif
            @if($isExternalSite && $project->docs_url)
                @if($project->category === \App\Enums\ProjectCategory::YoutubeChannel)
                    <a href="{{ $project->docs_url }}" aria-label="YouTube" rel="noopener" target="_blank">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                    </a>
                @else
                    <a href="{{ $project->docs_url }}" aria-label="Website" rel="noopener" target="_blank">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    </a>
                @endif
            @endif
        </div>
    </div>
</article>

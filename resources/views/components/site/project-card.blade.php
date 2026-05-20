@props(['project'])

@php
    $locale = \App\Support\LocaleSupport::short();
    $title = $project->getTranslation('title', $locale, false) ?: $project->getTranslation('title', 'pt', false);
@endphp

<article class="card project-card">
    <svg class="arrow-tr" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/>
    </svg>

    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 sm:gap-4 pr-6">
        <a href="{{ route('projects.show', ['slug' => $project->slug]) }}"
           class="mono text-[0.95rem] font-semibold text-ink-100 flex-1 min-w-0 break-words">{{ $project->name }}</a>
        <div class="flex items-center gap-2 flex-wrap sm:justify-end">
            @if($project->is_maintainer)
                <span class="badge badge-success" title="{{ __('Maintainer, not original author') }}">{{ __('maintainer') }}</span>
            @endif
            @if($project->is_daily_driver)
                <span class="badge badge-accent" title="{{ __('site.projects.badge_daily_driver') }}">@lang('site.projects.badge_daily_driver')</span>
            @endif
            @if($project->is_paid)
                <span class="badge badge-warning" title="{{ __('site.projects.badge_paid') }}">@lang('site.projects.badge_paid')</span>
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

    <div class="card-foot">
        <div class="card-meta-row">
            @unless($project->is_paid)
                <span>★ {{ $project->stars }}</span>
                @if($project->downloads_label)
                    <span>↓ {{ $project->downloads_label }}</span>
                @endif
                <span>⎘ {{ $project->license }}</span>
                @if($project->is_maintainer && $project->user_contributions > 0)
                    <span title="@lang('site.projects.label_contributions')">⎇ {{ number_format($project->user_contributions, 0, ',', '.') }}</span>
                @endif
            @endunless
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
        </div>
    </div>
</article>

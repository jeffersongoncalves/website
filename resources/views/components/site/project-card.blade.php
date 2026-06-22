@blaze
@props(['project'])

@php
    $locale = \JeffersonGoncalves\LocaleCookie\LocaleCookie::short();
    $title = $project->getTranslation('title', $locale, false) ?: $project->getTranslation('title', 'pt', false);
    // External-link projects (no repo → no stars/license): show the host
    // instead of the stars/license meta, same as the show page.
    $isExternalSite = in_array($project->category, [
        \App\Enums\ProjectCategory::Website,
        \App\Enums\ProjectCategory::YoutubeChannel,
        \App\Enums\ProjectCategory::Article,
    ], true);
    // Canonical section route: articles → /articles, external links → /links,
    // code → /projects (centralised on the model).
    $showRoute = $project->canonicalRouteName();
@endphp

<article class="card project-card">
    <svg class="arrow-tr" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/>
    </svg>

    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 sm:gap-4 pr-6">
        <h3 class="flex-1 min-w-0 m-0">
            <a href="{{ route($showRoute, ['slug' => $project->slug]) }}"
               class="mono text-[0.95rem] font-semibold text-ink-100 break-words">{{ $project->name }}</a>
        </h3>
        <div class="flex items-center gap-2 flex-wrap sm:justify-end">
            @if($project->isCreatedByOwner())
                <x-site.badge variant="success" :title="__('site.projects.badge_creator_help')">@lang('site.projects.badge_creator')</x-site.badge>
            @elseif($project->is_maintainer)
                <x-site.badge variant="success" :title="__('Maintainer, not original author')">{{ __('maintainer') }}</x-site.badge>
            @endif
            @if($project->is_daily_driver)
                <x-site.badge variant="accent" :title="__('site.projects.badge_daily_driver')">@lang('site.projects.badge_daily_driver')</x-site.badge>
            @endif
            @if($project->is_paid)
                <x-site.badge variant="warning" :title="__('site.projects.badge_paid')">@lang('site.projects.badge_paid')</x-site.badge>
            @endif
            <x-site.badge>{{ $project->category->getLabel() }}</x-site.badge>
        </div>
    </div>

    @if($title && $title !== $project->name)
        <p class="mt-3 body-sm">{{ $title }}</p>
    @endif

    @if(!empty($project->versions) || !empty($project->stack))
        <div class="flex flex-wrap gap-2 mt-4">
            @foreach($project->versions ?? [] as $v)
                <x-site.badge variant="accent">{{ $v }}</x-site.badge>
            @endforeach
            @foreach($project->stack ?? [] as $s)
                <x-site.badge>{{ $s }}</x-site.badge>
            @endforeach
        </div>
    @endif

    @if(!empty($project->topics))
        @php
            // External-link cards live on /links — point their topic chips at
            // that project's section there (?topic_<anchor>=…#<anchor>) instead
            // of /projects, which excludes external links entirely.
            $topicAnchor = $project->category->isExternalLink()
                ? ($project->category->linksSection() ?? $project->category->value)
                : null;
        @endphp
        <div class="flex flex-wrap gap-x-3 gap-y-1 mt-3 mono-meta-sm text-ink-400">
            @foreach(array_slice($project->topics, 0, 4) as $topic)
                @php
                    $topicHref = $topicAnchor !== null
                        ? route('links.index', ['topic_'.$topicAnchor => $topic]).'#'.$topicAnchor
                        : route('projects.index', ['topic' => $topic]);
                @endphp
                <a href="{{ $topicHref }}" class="hover:text-ink-200">#{{ $topic }}</a>
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
                    <span class="mono-meta-sm inline-flex items-center gap-1.5">
                        @if($project->category === \App\Enums\ProjectCategory::Website && ($faviconHost = parse_url((string) $project->docs_url, PHP_URL_HOST)))
                            {{-- Favicon thumb via our same-origin proxy (cached; keeps the
                                 browser off Google's S2 service).
                                 Decorative — the host text beside it carries the meaning. --}}
                            <img src="{{ route('favicon-proxy', ['domain' => $faviconHost]) }}"
                                 alt="" width="14" height="14" loading="lazy"
                                 class="rounded-[2px] shrink-0">
                        @else
                            <span aria-hidden="true">↗</span>
                        @endif
                        {{ $extLabel }}
                    </span>
                @endif
            @elseif(! $project->is_paid)
                {{-- Stars are GitHub stargazers — only meaningful with a repo. npm-only packages carry none. --}}
                @if($project->github_url)
                    <span aria-label="{{ $project->stars }} @lang('site.projects.label_stars')"><span aria-hidden="true">★</span> {{ $project->stars }}</span>
                @endif
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
                    <x-site.icon name="github"/>
                </a>
            @endif
            @if($project->packagist_url)
                <a href="{{ $project->packagist_url }}" aria-label="Packagist" rel="noopener" target="_blank">
                    <x-site.icon name="packagist"/>
                </a>
            @endif
            @if($project->docker_url)
                <a href="{{ $project->docker_url }}" aria-label="Docker" rel="noopener" target="_blank">
                    <x-site.icon name="docker"/>
                </a>
            @endif
            @if($isExternalSite && $project->docs_url)
                @if($project->category === \App\Enums\ProjectCategory::YoutubeChannel)
                    <a href="{{ $project->docs_url }}" aria-label="YouTube" rel="noopener" target="_blank">
                        <x-site.icon name="youtube"/>
                    </a>
                @else
                    <a href="{{ $project->docs_url }}" aria-label="Website" rel="noopener" target="_blank">
                        <x-site.icon name="website"/>
                    </a>
                @endif
            @endif
        </div>
    </div>
</article>

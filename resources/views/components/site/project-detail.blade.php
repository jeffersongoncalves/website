@blaze
@props([
    'project',
    'readmeHtml' => null,
    'versions' => [],
    'versionGroups' => [],
    'activeVersion' => null,
    'ref' => null,
])

@php
    $locale  = \App\Support\LocaleSupport::short();
    $title   = $project->getTranslation('title', $locale, false) ?: $project->name;

    // Canonical section route (centralised on the model): articles → /articles,
    // external links → /links, code → /projects. The back link and breadcrumb
    // follow it so the nav highlights match and "back" returns to the right
    // section — for /links, all the way to the section anchor it came from.
    $showRoute = $project->canonicalRouteName();
    $isArticleCategory = $project->category === \App\Enums\ProjectCategory::Article;
    $isExternalLink = $project->category->isExternalLink();

    if ($isArticleCategory) {
        $backUrl = route('articles.index');
        $backLabel = __('site.articles.back_to_list');
        $parentCrumb = ['name' => __('site.nav.articles'), 'url' => route('articles.index')];
    } elseif ($isExternalLink) {
        $section = $project->category->linksSection();
        $backUrl = route('links.index').($section ? '#'.$section : '');
        $backLabel = __('site.links.back_to_list');
        $parentCrumb = ['name' => __('site.nav.links'), 'url' => route('links.index')];
    } else {
        $backUrl = route('projects.index');
        $backLabel = __('site.projects.back_to_list');
        $parentCrumb = ['name' => __('site.nav.projects'), 'url' => route('projects.index')];
    }

    $breadcrumbs = [
        $parentCrumb,
        // Leaf crumb is the project NAME, not its (often sentence-long)
        // description — a breadcrumb label should read "Openclaw", not the tagline.
        ['name' => $project->name, 'url' => route($showRoute, ['slug' => $project->slug])],
    ];

    $initial = strtoupper(mb_substr($project->name, 0, 1));
    // External-link projects (no repo, no stars/license) — render a "visit"
    // card pointing at docs_url and skip the GitHub README / stats grid.
    $isExternalSite = in_array($project->category, [
        \App\Enums\ProjectCategory::Website,
        \App\Enums\ProjectCategory::YoutubeChannel,
        \App\Enums\ProjectCategory::Article,
    ], true);
    $externalUrl = $isExternalSite ? ($project->docs_url ?: $project->demo_url ?: $project->github_url) : null;
    // YouTube channels label with the handle (`@channel`) — the host is
    // identical across every channel and adds zero signal. Other external
    // sites show the hostname.
    if ($externalUrl
        && $project->category === \App\Enums\ProjectCategory::YoutubeChannel
        && preg_match('~youtube\.com/(@?[^/?#]+)~i', $externalUrl, $extMatch)) {
        $externalHost = $extMatch[1];
    } else {
        $externalHost = $externalUrl ? (parse_url($externalUrl, PHP_URL_HOST) ?: $externalUrl) : null;
    }

    $showUrl = route($showRoute, ['slug' => $project->slug]);

    // Emit the schema.org type that actually matches the project: an Article for
    // imported blog posts, a WebSite for external sites / YouTube channels, and
    // SoftwareSourceCode for the code repositories.
    $ldType = match ($project->category) {
        \App\Enums\ProjectCategory::Article => 'Article',
        \App\Enums\ProjectCategory::Website, \App\Enums\ProjectCategory::YoutubeChannel => 'WebSite',
        default => 'SoftwareSourceCode',
    };
    $isArticleLd = $ldType === 'Article';
    $mainLd = array_filter([
        '@context' => 'https://schema.org',
        '@type' => $ldType,
        'inLanguage' => str_replace('_', '-', app()->getLocale()),
        'name' => $project->name,
        'headline' => $isArticleLd ? $project->name : null,
        'description' => $title,
        'url' => ($isArticleLd || $ldType === 'WebSite') ? ($project->docs_url ?: $showUrl) : $showUrl,
        'codeRepository' => $ldType === 'SoftwareSourceCode' ? $project->github_url : null,
        'programmingLanguage' => $ldType === 'SoftwareSourceCode' ? $project->language?->value : null,
        'datePublished' => $isArticleLd ? $project->published_at?->toIso8601String() : null,
        'dateModified' => $isArticleLd ? $project->updated_at?->toIso8601String() : null,
        // Only claim authorship for repos that actually live under the owner's
        // GitHub account. The catalogue is mostly third-party (starred repos,
        // imported articles) — asserting `author: Jefferson` on those is false
        // structured data. array_filter drops the null for everything else.
        'author' => $project->isCreatedByOwner() ? [
            '@type' => 'Person',
            'name' => 'Jefferson Gonçalves',
            'url' => route('home'),
        ] : null,
    ], fn ($v) => $v !== null && $v !== '');

    $crumbItems = [];
    foreach (array_merge([['name' => __('site.common.home'), 'url' => route('home')]], $breadcrumbs) as $i => $crumb) {
        $crumbItems[] = [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $crumb['name'],
            'item' => $crumb['url'],
        ];
    }
    $breadcrumbLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'inLanguage' => str_replace('_', '-', app()->getLocale()),
        'itemListElement' => $crumbItems,
    ];
@endphp

@push('head')
    <x-site.json-ld :data="$mainLd"/>
    <x-site.json-ld :data="$breadcrumbLd"/>
    @if($readmeHtml)
        {{-- README images load from these hosts — warm the connections early.
             No crossorigin: <img> uses anonymous connections that wouldn't
             reuse a CORS-warmed socket. --}}
        <link rel="preconnect" href="https://raw.githubusercontent.com">
        <link rel="preconnect" href="https://user-images.githubusercontent.com">
        <link rel="preconnect" href="https://img.shields.io">
        <link rel="dns-prefetch" href="https://raw.githubusercontent.com">
        <link rel="dns-prefetch" href="https://user-images.githubusercontent.com">
        <link rel="dns-prefetch" href="https://img.shields.io">
    @endif
@endpush

{{-- Layout is supplied by the Livewire full-page component (ProjectShowPage)
     via ->layout(..., ['seoData' => $project]); this partial renders only the
     page body so it isn't double-wrapped. --}}
    <section class="section project-page-section">
        <div class="wrap">

            {{-- Breadcrumb back link --}}
            <a href="{{ $backUrl }}" class="project-back" id="top">
                {{ $backLabel }}
            </a>

            {{-- Compact project header --}}
            <header class="project-header">
                <div class="project-header-icon" aria-hidden="true">{{ $initial }}</div>
                <div class="project-header-text">
                    <h1 class="project-title">{{ $project->name }}</h1>
                    @if($title && $title !== $project->name)
                        <p class="project-subtitle">{{ $title }}</p>
                    @endif
                </div>
            </header>

            {{-- Two-column layout: README main + sticky sidebar --}}
            <div class="project-layout">

                <main class="project-main">

                    {{-- On this page ToC (dropdown, auto-populated from markdown headings) --}}
                    @if($readmeHtml)
                        <div class="on-this-page"
                             x-data="onThisPage"
                             x-init="build()"
                             @click.outside="open = false"
                             @keydown.escape.window="open = false">
                            <button type="button"
                                    class="on-this-page-btn"
                                    @click="open = !open"
                                    :aria-expanded="open.toString()">
                                <span>@lang('site.projects.on_this_page')</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" :class="{'rotate-180': open}" style="transition:transform 200ms var(--ease-out);"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <ul class="on-this-page-menu"
                                x-show="open"
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 -translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                x-cloak>
                                <template x-for="h in headings" :key="h.id">
                                    <li>
                                        <a :href="'#' + h.id"
                                           :class="'on-this-page-link otp-level-' + h.level"
                                           @click="open = false"
                                           x-text="h.text"></a>
                                    </li>
                                </template>
                                <template x-if="headings.length === 0">
                                    <li class="on-this-page-empty">@lang('site.projects.no_headings')</li>
                                </template>
                            </ul>
                        </div>
                    @endif

                    {{-- Version selector --}}
                    @if(!empty($versionGroups))
                        <div class="version-selector">
                            <span class="mono-meta">@lang('site.projects.version_label')</span>
                            @foreach($versionGroups as $group)
                                {{-- wire:click switches the README in place; the href keeps it a
                                     real, shareable ?v= URL (and a no-JS fallback). Versions that
                                     share a branch render as one chip (e.g. "v4/v5"). --}}
                                <a href="{{ route($showRoute, ['slug' => $project->slug, 'v' => $group['param']]) }}#top"
                                   wire:click.prevent="setVersion(@js($group['param']))"
                                   class="chip {{ in_array($activeVersion, $group['versions'], true) ? 'chip-active' : '' }}">
                                    {{ $group['label'] }}
                                </a>
                            @endforeach
                            @if($ref)
                                <span class="mono-meta">·</span>
                                <span class="mono-meta">branch: <code class="inline">{{ $ref }}</code></span>
                            @endif
                        </div>
                    @endif

                    {{-- README markdown --}}
                    @if($readmeHtml)
                        <div class="markdown-body" x-data="markdownCopy" x-init="enhance()">
                            {!! $readmeHtml !!}
                        </div>
                    @elseif($isExternalSite && $externalUrl)
                        @php
                            $isArticle = $project->category === \App\Enums\ProjectCategory::Article;
                            // Articles carry an authored body in the translatable
                            // `content` column — render it as the page content,
                            // with the external link as a "read original" CTA.
                            $articleBody = $isArticle
                                ? ($project->getTranslation('content', $locale, false) ?: $project->getTranslation('content', 'pt', false))
                                : null;
                        @endphp
                        @if($articleBody)
                            <article class="markdown-body">{!! \Illuminate\Support\Str::markdown($articleBody) !!}</article>
                        @endif
                        <div class="card flex flex-col gap-5 {{ $articleBody ? 'mt-8' : '' }}">
                            @unless($articleBody)
                                <p class="body-text">@lang($isArticle ? 'site.projects.article_blurb' : 'site.projects.external_site_blurb')</p>
                            @endunless
                            <a href="{{ $externalUrl }}" rel="noopener" target="_blank" class="btn btn-primary self-start inline-flex items-center gap-3">
                                <span>@lang($isArticle ? 'site.projects.article_read' : 'site.projects.external_site_visit')</span>
                                <span class="mono-meta-sm opacity-70">{{ $externalHost }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
                            </a>
                        </div>
                    @else
                        @if($project->is_paid)
                            <p class="body-text" style="color:var(--text-muted);">
                                @lang('site.projects.readme_paid')
                                @if($project->docs_url)
                                    <a href="{{ $project->docs_url }}" rel="noopener" target="_blank" class="text-amber">{{ $project->docs_url }}</a>
                                @endif
                            </p>
                        @elseif($project->github_url)
                            <p class="body-text" style="color:var(--text-muted);">
                                @lang('site.projects.readme_unavailable')
                                <a href="{{ $project->github_url }}" rel="noopener" target="_blank" class="text-amber">{{ $project->github_url }}</a>
                            </p>
                        @else
                            <p class="body-text" style="color:var(--text-muted);">
                                @lang('site.projects.readme_unavailable_no_source')
                            </p>
                        @endif
                    @endif

                </main>

                <aside class="project-sidebar">

                    {{-- Card 1: primary actions --}}
                    <div class="card project-actions-card">
                        @if($isExternalSite && $externalUrl)
                            <a href="{{ $externalUrl }}" rel="noopener" target="_blank" class="btn btn-primary project-action-btn">
                                @lang('site.projects.external_site_visit')
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
                            </a>
                        @else
                            @if($project->github_url)
                                <a href="{{ $project->github_url }}" rel="noopener" target="_blank" class="btn btn-primary project-action-btn">
                                    GitHub
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
                                </a>
                            @endif
                            @if($project->packagist_url)
                                <a href="{{ $project->packagist_url }}" rel="noopener" target="_blank" class="btn btn-secondary project-action-btn">Packagist <span aria-hidden="true">↗</span></a>
                            @endif
                            @if($project->docker_url)
                                <a href="{{ $project->docker_url }}" rel="noopener" target="_blank" class="btn btn-secondary project-action-btn">Docker <span aria-hidden="true">↗</span></a>
                            @endif
                            @if($project->docs_url)
                                <a href="{{ $project->docs_url }}" rel="noopener" target="_blank" class="btn btn-secondary project-action-btn">@lang('site.projects.action_docs') <span aria-hidden="true">↗</span></a>
                            @endif
                            @if($project->demo_url)
                                <a href="{{ $project->demo_url }}" rel="noopener" target="_blank" class="btn btn-secondary project-action-btn">@lang('site.projects.action_demo') <span aria-hidden="true">↗</span></a>
                            @endif
                        @endif
                    </div>

                    {{-- Card 2: project details --}}
                    <div class="card project-details-card">
                        <h3 class="project-details-title">@lang('site.projects.details_title')</h3>

                        <div class="project-detail-row">
                            <span class="project-detail-label">@lang('site.projects.label_type')</span>
                            <span class="project-detail-value">{{ $project->category->getLabel() }}</span>
                        </div>

                        @if($project->isCreatedByOwner())
                            <div class="project-detail-row">
                                <span class="project-detail-label">@lang('site.projects.label_role')</span>
                                <span class="badge badge-success">@lang('site.projects.badge_creator')</span>
                            </div>
                        @elseif($project->is_maintainer)
                            <div class="project-detail-row">
                                <span class="project-detail-label">@lang('site.projects.label_role')</span>
                                <span class="badge badge-success">@lang('maintainer')</span>
                            </div>
                        @endif

                        @if($project->is_daily_driver)
                            <div class="project-detail-row">
                                <span class="project-detail-label">@lang('site.projects.label_role')</span>
                                <span class="badge badge-accent">@lang('site.projects.badge_daily_driver')</span>
                            </div>
                        @endif

                        @if($project->is_paid)
                            <div class="project-detail-row">
                                <span class="project-detail-label">@lang('site.projects.label_role')</span>
                                <span class="badge badge-warning">@lang('site.projects.badge_paid')</span>
                            </div>
                        @endif

                        @php
                            $showStatsGrid = ! $project->is_paid && ! $isExternalSite;
                            $showVersionsStat = ! empty($project->versions);
                            $showContribStat = $project->is_maintainer && $project->user_contributions > 0;
                        @endphp
                        @if($showStatsGrid || $showVersionsStat || $showContribStat)
                            <div class="project-detail-grid">
                                @if($showStatsGrid)
                                    <div class="project-detail-stat">
                                        <small>@lang('site.projects.label_stars')</small>
                                        <strong><span aria-hidden="true">★</span> {{ $project->stars }}</strong>
                                    </div>
                                    @if($project->downloads_label)
                                        <div class="project-detail-stat">
                                            <small>@lang('site.projects.label_downloads')</small>
                                            <strong><span aria-hidden="true">↓</span> {{ $project->downloads_label }}</strong>
                                        </div>
                                    @endif
                                    <div class="project-detail-stat">
                                        <small>@lang('site.projects.label_license')</small>
                                        <strong>{{ $project->license ?: '—' }}</strong>
                                    </div>
                                @endif
                                @if($showVersionsStat)
                                    <div class="project-detail-stat">
                                        <small>@lang('site.projects.label_versions')</small>
                                        <strong>{{ implode(' · ', $project->versions) }}</strong>
                                    </div>
                                @endif
                                @if($showContribStat)
                                    <div class="project-detail-stat">
                                        <small>@lang('site.projects.label_contributions')</small>
                                        <strong><span aria-hidden="true">⎘</span> {{ number_format($project->user_contributions, 0, ',', '.') }}</strong>
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if(!empty($project->stack))
                            <div class="project-detail-row project-detail-row-stack">
                                <span class="project-detail-label">@lang('site.projects.label_stack')</span>
                                <div class="project-detail-stack">
                                    @foreach($project->stack as $s)
                                        <span class="badge">{{ $s }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(!empty($project->topics))
                            <div class="project-detail-row project-detail-row-stack">
                                <span class="project-detail-label">@lang('site.projects.label_topics')</span>
                                <div class="project-detail-stack">
                                    @php
                                        $topicAnchor = $isExternalLink
                                            ? ($project->category->linksSection() ?? $project->category->value)
                                            : null;
                                    @endphp
                                    @foreach($project->topics as $topic)
                                        @php
                                            $topicHref = $topicAnchor !== null
                                                ? route('links.index', ['topic_'.$topicAnchor => $topic]).'#'.$topicAnchor
                                                : route('projects.index', ['topic' => $topic]);
                                        @endphp
                                        <a href="{{ $topicHref }}" class="badge">#{{ $topic }}</a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($project->updated_at)
                            <div class="project-detail-row project-detail-row-footer">
                                <span class="project-detail-label">@lang('site.projects.label_updated')</span>
                                <span class="project-detail-value mono-meta-sm">{{ $project->updated_at->diffForHumans() }}</span>
                            </div>
                        @endif
                    </div>

                </aside>
            </div>

        </div>
    </section>

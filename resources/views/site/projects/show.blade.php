@php
    $locale  = \App\Support\LocaleSupport::short();
    $title   = $project->getTranslation('title', $locale, false) ?: $project->name;

    $breadcrumbs = [
        ['name' => __('site.nav.projects'), 'url' => route('projects.index')],
        ['name' => $title, 'url' => route('projects.show', ['slug' => $project->slug])],
    ];

    $initial = strtoupper(mb_substr($project->name, 0, 1));
@endphp

<x-site.layouts.app :title="$project->name" :breadcrumbs="$breadcrumbs" :seoData="$project">

    <section class="section project-page-section">
        <div class="wrap">

            {{-- Breadcrumb back link --}}
            <a href="{{ route('projects.index') }}" class="project-back" id="top">
                @lang('site.projects.back_to_list')
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
                    @if(!empty($versions))
                        <div class="version-selector">
                            <span class="mono-meta">@lang('site.projects.version_label')</span>
                            @foreach($versions as $v)
                                <a href="{{ route('projects.show', ['slug' => $project->slug, 'v' => $v]) }}#top"
                                   class="chip {{ $activeVersion === $v ? 'chip-active' : '' }}">
                                    {{ $v }}
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
                        @if($project->github_url)
                            <a href="{{ $project->github_url }}" rel="noopener" target="_blank" class="btn btn-primary project-action-btn">
                                GitHub
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
                            </a>
                        @endif
                        @if($project->packagist_url)
                            <a href="{{ $project->packagist_url }}" rel="noopener" target="_blank" class="btn btn-secondary project-action-btn">Packagist ↗</a>
                        @endif
                        @if($project->docs_url)
                            <a href="{{ $project->docs_url }}" rel="noopener" target="_blank" class="btn btn-secondary project-action-btn">Docs ↗</a>
                        @endif
                        @if($project->demo_url)
                            <a href="{{ $project->demo_url }}" rel="noopener" target="_blank" class="btn btn-secondary project-action-btn">Demo ↗</a>
                        @endif
                    </div>

                    {{-- Card 2: project details --}}
                    <div class="card project-details-card">
                        <h3 class="project-details-title">@lang('site.projects.details_title')</h3>

                        <div class="project-detail-row">
                            <span class="project-detail-label">@lang('site.projects.label_type')</span>
                            <span class="project-detail-value">{{ $project->category->getLabel() }}</span>
                        </div>

                        @if($project->is_maintainer)
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

                        <div class="project-detail-grid">
                            <div class="project-detail-stat">
                                <small>@lang('site.projects.label_stars')</small>
                                <strong>★ {{ $project->stars }}</strong>
                            </div>
                            <div class="project-detail-stat">
                                <small>@lang('site.projects.label_downloads')</small>
                                <strong>↓ {{ $project->downloads_label ?: '—' }}</strong>
                            </div>
                            <div class="project-detail-stat">
                                <small>@lang('site.projects.label_license')</small>
                                <strong>{{ $project->license ?: '—' }}</strong>
                            </div>
                            @if(!empty($project->versions))
                                <div class="project-detail-stat">
                                    <small>@lang('site.projects.label_versions')</small>
                                    <strong>{{ implode(' · ', $project->versions) }}</strong>
                                </div>
                            @endif
                            @if($project->is_maintainer && $project->user_contributions > 0)
                                <div class="project-detail-stat">
                                    <small>@lang('site.projects.label_contributions')</small>
                                    <strong>⎘ {{ number_format($project->user_contributions, 0, ',', '.') }}</strong>
                                </div>
                            @endif
                        </div>

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

</x-site.layouts.app>

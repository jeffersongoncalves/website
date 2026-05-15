@php
    $locale  = \App\Support\LocaleSupport::short();
    $title   = $project->getTranslation('title', $locale, false) ?: $project->name;
    $desc    = $project->getTranslation('description', $locale, false) ?: $project->getTranslation('description', 'pt', false);
    $content = $project->getTranslation('content', $locale, false) ?: $project->getTranslation('content', 'pt', false);

    $breadcrumbs = [
        ['name' => __('site.nav.projects'), 'url' => route('projects.index')],
        ['name' => $title, 'url' => route('projects.show', ['slug' => $project->slug])],
    ];
@endphp

<x-site.layouts.app :title="$project->name" :description="$desc" :breadcrumbs="$breadcrumbs" :seoData="$project">

    <article class="section" style="border-top:none;padding-top:var(--s-9);">
        <div class="wrap" style="max-width:880px;">
            <x-site.eyebrow num="01" label="projeto"/>

            <div class="flex items-center flex-wrap"
                 style="gap:var(--s-3);font-family:var(--font-mono);font-size:0.8125rem;color:var(--ink-500);">
                <span class="badge">{{ $project->category->getLabel() }}</span>
                @if($project->is_maintainer)
                    <span class="badge badge-success" title="{{ __('Maintainer, not original author') }}">{{ __('maintainer') }}</span>
                @endif
                <span>★ {{ $project->stars }}</span>
                <span>·</span>
                <span>↓ {{ $project->downloads_label ?: '—' }}</span>
                <span>·</span>
                <span>⎘ {{ $project->license }}</span>
            </div>

            <h1 style="margin-top:var(--s-5);font-size:clamp(36px,6vw,72px);font-weight:400;letter-spacing:-0.025em;line-height:1.05;">
                {{ $project->name }}
            </h1>

            <p style="margin-top:var(--s-5);font-size:1.125rem;line-height:1.6;max-width:62ch;color:var(--ink-300);">
                {{ $desc }}
            </p>

            @if(!empty($project->versions) || !empty($project->stack))
                <div class="flex flex-wrap" style="margin-top:var(--s-6);gap:var(--s-2);">
                    @foreach($project->versions ?? [] as $v)
                        <span class="badge badge-accent">{{ $v }}</span>
                    @endforeach
                    @foreach($project->stack ?? [] as $s)
                        <span class="badge">{{ $s }}</span>
                    @endforeach
                </div>
            @endif

            <div class="flex flex-wrap" style="margin-top:var(--s-6);gap:var(--s-3);">
                <a href="{{ $project->github_url }}" rel="noopener" target="_blank" class="btn btn-primary">
                    GitHub
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
                </a>
                @if($project->packagist_url)
                    <a href="{{ $project->packagist_url }}" rel="noopener" target="_blank" class="btn btn-secondary">Packagist</a>
                @endif
                @if($project->docs_url)
                    <a href="{{ $project->docs_url }}" rel="noopener" target="_blank" class="btn btn-secondary">Docs</a>
                @endif
                @if($project->demo_url)
                    <a href="{{ $project->demo_url }}" rel="noopener" target="_blank" class="btn btn-secondary">Demo</a>
                @endif
            </div>
        </div>
    </article>

    @if($content)
        <div class="divider"></div>
        <section class="section">
            <div class="wrap" style="max-width:760px;">
                <div style="color:var(--ink-300);font-size:1.0625rem;line-height:1.75;white-space:pre-wrap;">{{ $content }}</div>
            </div>
        </section>
    @endif

    <div class="divider"></div>

    <section class="section">
        <div class="wrap" style="max-width:880px;">
            <x-site.eyebrow num="02" label="readme"/>

            @if(!empty($versions))
                <div class="flex items-center flex-wrap gap-2 mb-6 mono-meta">
                    <span>@lang('site.projects.version_label')</span>
                    @foreach($versions as $v)
                        <a href="{{ route('projects.show', ['slug' => $project->slug, 'v' => $v]) }}#top"
                           class="chip {{ $activeVersion === $v ? 'chip-active' : '' }}">
                            {{ $v }}
                        </a>
                    @endforeach
                    @if($ref)
                        <span class="text-ink-500">·</span>
                        <span>branch: <code class="inline">{{ $ref }}</code></span>
                    @endif
                </div>
            @endif

            @if($readmeHtml)
                <div class="markdown-body" x-data="markdownCopy" x-init="enhance()">
                    {!! $readmeHtml !!}
                </div>
            @else
                <p class="body-text" style="color:var(--ink-400);">
                    @lang('site.projects.readme_unavailable')
                    <a href="{{ $project->github_url }}" rel="noopener" target="_blank" class="text-amber">{{ $project->github_url }}</a>
                </p>
            @endif
        </div>
    </section>

    <div class="divider"></div>

    <section class="section">
        <div class="wrap" style="max-width:760px;">
            <a href="{{ route('projects.index') }}"
               class="btn-ghost" style="font-family:var(--font-mono);font-size:0.9375rem;color:var(--ink-200);">
                @lang('site.projects.back_to_list')
            </a>
        </div>
    </section>

</x-site.layouts.app>

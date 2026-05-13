@php
    $locale = app()->getLocale();
    $title   = $post->getTranslation('title',   $locale, false) ?: $post->getTranslation('title', 'pt', false);
    $excerpt = $post->getTranslation('excerpt', $locale, false) ?: $post->getTranslation('excerpt', 'pt', false);
    $body    = $post->getTranslation('body',    $locale, false) ?: $post->getTranslation('body', 'pt', false);
@endphp

<x-site.layouts.app :title="$title" :description="$excerpt">

    <article class="section section-first">
        <div class="wrap-prose">
            <x-site.eyebrow num="01" label="post"/>

            <div class="flex items-center flex-wrap gap-3 mono-meta-sm">
                <span>{{ $post->published_at?->isoFormat('DD MMM YYYY') }}</span>
                <span>·</span>
                <span>{{ $post->reading_time }} @lang('site.blog.min_read')</span>
                @if($post->views)
                    <span>·</span>
                    <span>{{ number_format($post->views, 0, ',', '.') }} @lang('site.blog.views')</span>
                @endif
            </div>

            <h1 class="h-page mt-6">{{ $title }}</h1>

            @if($excerpt)
                <p class="lede mt-6">{{ $excerpt }}</p>
            @endif

            @if(!empty($post->tags))
                <div class="flex flex-wrap gap-2 mt-8">
                    @foreach($post->tags as $tag)
                        <span class="badge">{{ $tag }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </article>

    <div class="divider"></div>

    <section class="section">
        <div class="wrap-prose">
            <div class="body-text whitespace-pre-wrap">{{ $body }}</div>

            <div class="mt-16 pt-8 border-t border-ink-800">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div class="mono-meta">
                        // @lang('site.common.updated_in') {{ $post->updated_at?->isoFormat('DD MMM YYYY') }}
                    </div>
                    <a href="{{ route('blog.index', ['locale' => $locale]) }}" class="btn-ghost mono text-[0.875rem] text-ink-200">
                        @lang('site.blog.back_to_list')
                    </a>
                </div>
            </div>
        </div>
    </section>

    @if($related->isNotEmpty())
        <div class="divider"></div>
        <section class="section">
            <div class="wrap">
                <x-site.eyebrow num="02" label="próximos posts"/>
                <h2 class="h-section mb-12">@lang('site.blog.continue')</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($related as $r)
                        @php
                            $rTitle = $r->getTranslation('title', $locale, false) ?: $r->getTranslation('title', 'pt', false);
                            $rExc   = $r->getTranslation('excerpt', $locale, false) ?: $r->getTranslation('excerpt', 'pt', false);
                        @endphp
                        <a href="{{ route('blog.show', ['locale' => $locale, 'slug' => $r->slug]) }}" class="card flex flex-col">
                            <div class="mono-meta">
                                {{ $r->published_at?->isoFormat('DD MMM YYYY') }} · {{ $r->reading_time }} @lang('site.blog.min_read')
                            </div>
                            <h3 class="h-card mt-3">{{ $rTitle }}</h3>
                            @if($rExc)
                                <p class="body-sm mt-3">{{ $rExc }}</p>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

</x-site.layouts.app>

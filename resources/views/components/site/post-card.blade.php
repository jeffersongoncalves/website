@props(['post'])

@php
    $locale = app()->getLocale();
    $title   = $post->getTranslation('title',   $locale, false) ?: $post->getTranslation('title', 'pt', false);
    $excerpt = $post->getTranslation('excerpt', $locale, false) ?: $post->getTranslation('excerpt', 'pt', false);
@endphp

<a href="{{ route('blog.show', ['locale' => $locale, 'slug' => $post->slug]) }}"
   class="card project-card flex flex-col md:flex-row md:items-center gap-6">
    <svg class="arrow-tr" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/>
    </svg>

    <div class="md:w-[160px] flex-none mono-meta">
        <div>{{ $post->published_at?->isoFormat('DD MMM YYYY') }}</div>
        <div>{{ $post->reading_time }} @lang('site.blog.min_read')</div>
    </div>

    <div class="flex-1 pr-6">
        <h3 class="text-[1.25rem] font-medium leading-[1.35] tracking-tight text-ink-100">{{ $title }}</h3>
        @if($excerpt)
            <p class="mt-2 body-sm">{{ $excerpt }}</p>
        @endif
        @if(!empty($post->tags))
            <div class="flex flex-wrap gap-2 mt-3">
                @foreach($post->tags as $tag)
                    <span class="badge">{{ $tag }}</span>
                @endforeach
            </div>
        @endif
    </div>
</a>

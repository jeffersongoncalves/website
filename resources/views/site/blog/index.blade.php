@php $locale = app()->getLocale(); @endphp

<x-site.layouts.app :title="__('site.nav.blog')">

    <section class="section section-first">
        <div class="wrap">
            <x-site.eyebrow num="01" :label="__('site.nav.blog')"/>
            <h1>
                @lang('site.blog.title_1')<br>
                <span class="h-sub">@lang('site.blog.title_2')</span>
            </h1>
            <p class="lede mt-6">@lang('site.blog.sub')</p>
        </div>
    </section>

    <div class="divider"></div>

    <section class="section">
        <div class="wrap">
            <div class="flex flex-wrap items-center gap-3 mb-10">
                <x-site.eyebrow num="02" label="posts"/>
                <div class="overflow-x-auto no-scrollbar flex-1">
                    <div class="flex gap-2 min-w-max justify-end">
                        <a href="{{ route('blog.index', ['locale' => $locale]) }}"
                           class="chip {{ $activeTag === 'all' ? 'chip-active' : '' }}">@lang('site.common.all')</a>
                        @foreach($tags as $tag)
                            <a href="{{ route('blog.index', ['locale' => $locale, 'tag' => $tag]) }}"
                               class="chip {{ $activeTag === $tag ? 'chip-active' : '' }}">{{ $tag }}</a>
                        @endforeach
                    </div>
                </div>
            </div>

            @if($posts->isEmpty())
                <div class="text-center py-16 mono text-sm text-ink-500">@lang('site.common.no_results')</div>
            @else
                <div class="flex flex-col gap-3">
                    @foreach($posts as $post)
                        <x-site.post-card :post="$post"/>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <div class="divider"></div>

    <section class="section">
        <div class="wrap">
            <x-site.eyebrow num="03" :label="__('site.blog.follow_title')"/>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <a href="/feed.xml" class="card flex items-center justify-between">
                    <div>
                        <h3 class="h-card">@lang('site.blog.rss_title')</h3>
                        <p class="mt-1 mono-meta">@lang('site.blog.rss_url')</p>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-amber"><path d="M4 11a9 9 0 0 1 9 9"/><path d="M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/></svg>
                </a>
                <a href="{{ route('contact', ['locale' => $locale]) }}" class="card flex items-center justify-between">
                    <div>
                        <h3 class="h-card">@lang('site.blog.newsletter_title')</h3>
                        <p class="mt-1 mono-meta">@lang('site.blog.newsletter_sub')</p>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-amber"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg>
                </a>
            </div>
        </div>
    </section>

</x-site.layouts.app>

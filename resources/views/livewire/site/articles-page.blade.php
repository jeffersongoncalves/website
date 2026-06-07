<div>
    @push('head')
        <link rel="alternate" type="application/rss+xml" title="{{ __('site.articles.title') }}" href="{{ route('articles.feed') }}">
    @endpush
    <section class="section section-first">
        <div class="wrap">
            <x-site.eyebrow num="01" :label="__('site.articles.eyebrow')"/>
            <h1>@lang('site.articles.title')</h1>
            <p class="lede mt-6">@lang('site.articles.sub')</p>
            <p class="mt-6 mono-meta-sm">
                <a href="{{ route('articles.feed') }}" rel="alternate" type="application/rss+xml" class="text-ink-400 hover:text-ink-200">
                    @lang('site.articles.feed_link') <span aria-hidden="true">↗</span>
                </a>
            </p>
        </div>
    </section>

    <div class="divider"></div>

    <section class="section" id="articles" style="scroll-margin-top: 6rem;">
        <div class="wrap">
            <livewire:site.articles-list/>
        </div>
    </section>
</div>

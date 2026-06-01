<x-site.layouts.app :title="__('site.articles.title')" :description="__('site.articles.sub')">

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

    <section class="section">
        <div class="wrap">
            @if($articles->isEmpty())
                <div class="text-center py-16 mono text-sm text-ink-500">@lang('site.articles.empty')</div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($articles as $project)
                        <x-site.project-card :project="$project"/>
                    @endforeach
                </div>

                <div class="mt-10">
                    {{ $articles->onEachSide(1)->links() }}
                </div>
            @endif
        </div>
    </section>

</x-site.layouts.app>

<x-site.layouts.app :title="__('site.offline.title')" :description="__('site.offline.message')">

    <section class="section section-first">
        <div class="wrap">
            <x-site.eyebrow num="0x" :label="__('site.offline.eyebrow')"/>
            <h1>
                @lang('site.offline.heading_1')<br>
                <span class="h-sub">@lang('site.offline.heading_2')</span>
            </h1>
            <p class="lede mt-6">@lang('site.offline.message')</p>

            <div class="mt-10 flex items-center gap-6">
                <button type="button"
                        onclick="window.location.reload()"
                        class="btn btn-primary">
                    @lang('site.offline.retry')
                </button>
                <a href="{{ route('home') }}" class="mono text-ink-400 hover:text-amber">
                    @lang('site.offline.home') →
                </a>
            </div>
        </div>
    </section>

</x-site.layouts.app>

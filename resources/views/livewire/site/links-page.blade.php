<div>
    <section class="section section-first">
        <div class="wrap">
            <x-site.eyebrow num="01" :label="__('site.nav.links')"/>
            <h1>@lang('site.links.title')</h1>
            <p class="lede mt-6">@lang('site.links.sub')</p>
        </div>
    </section>

    <div class="divider"></div>

    @if(count($sections) === 0)
        <section class="section">
            <div class="wrap">
                <div class="text-center py-16 mono text-sm text-ink-500">@lang('site.common.no_results')</div>
            </div>
        </section>
    @else
        @foreach($sections as $i => $cat)
            <livewire:site.links-section :category="$cat" :index="$i" :key="$cat->value"/>
            @unless($loop->last)
                <div class="divider"></div>
            @endunless
        @endforeach
    @endif
</div>

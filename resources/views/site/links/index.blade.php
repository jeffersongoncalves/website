<x-site.layouts.app :title="__('site.links.title')" :description="__('site.seo.links')">

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
        @foreach($sections as $i => $section)
            @php $cat = $section['category']; $projects = $section['projects']; @endphp
            <section class="section" id="{{ $section['anchor'] }}" style="scroll-margin-top: 6rem;">
                <div class="wrap">
                    <x-site.eyebrow :num="sprintf('%02d', $i + 2)" :label="__('site.links.section_'.$cat->value)"/>
                    <h2 class="h-section">{{ __('site.links.section_'.$cat->value) }}</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
                        @foreach($projects as $project)
                            <x-site.project-card :project="$project"/>
                        @endforeach
                    </div>

                    @if($projects->hasPages())
                        <div class="mt-10">
                            {{ $projects->onEachSide(1)->links() }}
                        </div>
                    @endif
                </div>
            </section>
            @unless($loop->last)
                <div class="divider"></div>
            @endunless
        @endforeach
    @endif

</x-site.layouts.app>

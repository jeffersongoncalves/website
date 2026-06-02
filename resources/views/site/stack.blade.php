@php $locale = \App\Support\LocaleSupport::short(); @endphp

<x-site.layouts.app :title="__('site.stack.title')" :description="__('site.seo.stack')">

    <section class="section section-first">
        <div class="wrap">
            <x-site.eyebrow num="01" :label="__('site.nav.stack')"/>
            <h1>@lang('site.stack.title')</h1>
            <p class="lede mt-6 max-w-[60ch]">@lang('site.stack.sub')</p>
            <p class="mt-4 mono-meta-sm text-ink-500 max-w-[60ch]">@lang('site.stack.version_note')</p>
        </div>
    </section>

    <div class="divider"></div>

    <section class="section">
        <div class="wrap flex flex-col gap-16">
            @foreach($groups as $i => $group)
                <div>
                    <x-site.eyebrow :num="str_pad((string)($i + 2), 2, '0', STR_PAD_LEFT)" :label="$group[$locale] ?? $group['en']"/>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-8">
                        @foreach($group['items'] as $item)
                            <div class="card">
                                <div class="flex items-baseline justify-between gap-3">
                                    <h3 class="h-card">{{ $item['name'] }}</h3>
                                    @if($item['version'])
                                        <span class="badge badge-accent">{{ $item['version'] }}</span>
                                    @endif
                                </div>
                                <p class="body-sm mt-3">{{ $item[$locale] ?? $item['en'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>

</x-site.layouts.app>

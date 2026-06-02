@php $locale = \App\Support\LocaleSupport::short(); @endphp

<div>
    <section class="section section-first">
        <div class="wrap">
            <x-site.eyebrow num="01" :label="__('site.nav.stack')"/>
            <h1>@lang('site.stack.title')</h1>
            <p class="lede mt-6 max-w-[60ch]">@lang('site.stack.sub')</p>
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
                                    <h3 class="h-card break-words min-w-0">
                                        @if(!empty($item['url']))
                                            <a href="{{ $item['url'] }}" class="hover:text-amber" @unless(!empty($item['internal'])) rel="noopener" target="_blank" @endunless>{{ $item['name'] }}</a>
                                        @else
                                            {{ $item['name'] }}
                                        @endif
                                    </h3>
                                    @if(!empty($item['version']))
                                        <span class="badge badge-accent shrink-0">{{ $item['version'] }}</span>
                                    @endif
                                </div>
                                @if(!empty($item['author']))
                                    <span class="badge badge-success mt-3 inline-block" title="{{ __('site.stack.mine') }}">@lang('site.stack.mine')</span>
                                @endif
                                <p class="body-sm mt-3">{{ $item[$locale] ?? $item['en'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</div>

@php
    $locale = app()->getLocale();
    $sent = session('contact_sent', false);
@endphp

<x-site.layouts.app :title="__('site.nav.contact')">

    <section class="section section-first">
        <div class="wrap">
            <x-site.eyebrow num="01" :label="__('site.nav.contact')"/>
            <h1>
                @lang('site.contact.title_1')<br>
                <span class="h-sub">@lang('site.contact.title_2')</span>
            </h1>
            <p class="lede mt-6">@lang('site.contact.sub')</p>
        </div>
    </section>

    <div class="divider"></div>

    <section class="section" x-data="{ kind: '{{ old('kind', 'consultoria') }}', sent: {{ $sent ? 'true' : 'false' }} }">
        <div class="wrap">
            <x-site.eyebrow num="02" label="canais"/>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-16">
                <div class="lg:col-span-5">
                    <h2 class="h-section">@lang('site.contact.where_title')</h2>
                    <p class="body-text mt-6">@lang('site.contact.where_body')</p>

                    <ul class="flex flex-col gap-3 mt-10 list-none">
                        @foreach ([
                            ['url' => config('site.social.github'),    'label' => 'GitHub',      'sub' => '@jeffersongoncalves · 5.4k followers'],
                            ['url' => config('site.social.linkedin'),  'label' => 'LinkedIn',    'sub' => 'in/jeffersonsimaogoncalves'],
                            ['url' => config('site.social.packagist'), 'label' => 'Packagist',   'sub' => 'jeffersongoncalves · 35+ pacotes'],
                            ['url' => config('site.social.x'),         'label' => 'X (Twitter)', 'sub' => '@gersonsimao92'],
                            ['url' => 'mailto:' . config('site.social.email'), 'label' => 'E-mail', 'sub' => config('site.social.email')],
                        ] as $ch)
                            <li>
                                <a href="{{ $ch['url'] }}" rel="noopener" target="_blank" class="card card-pad-md flex items-center gap-4">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-[0.9375rem] text-ink-100">{{ $ch['label'] }}</div>
                                        <div class="mt-0.5 mono-meta truncate">{{ $ch['sub'] }}</div>
                                    </div>
                                    <span class="text-ink-400">→</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="lg:col-span-7">
                    <div class="card card-pad-lg">
                        <div class="mono-tag mb-8">@lang('site.contact.form_title')</div>

                        <div x-show="!sent">
                            <div class="mb-12">
                                <label class="field-label">@lang('site.contact.kind')</label>
                                <div class="flex flex-wrap gap-2 mt-3">
                                    @foreach($kinds as $key => $labels)
                                        <button type="button"
                                                @click="kind = '{{ $key }}'"
                                                :class="kind === '{{ $key }}' ? 'chip chip-active' : 'chip'">
                                            {{ $labels[$locale] ?? $labels['pt'] }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <form method="POST" action="{{ route('contact.submit', ['locale' => $locale]) }}" class="flex flex-col gap-6">
                                @csrf
                                <input type="hidden" name="kind" :value="kind">

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="field-label">@lang('site.contact.name')</label>
                                        <input type="text" name="name" required value="{{ old('name') }}"
                                               class="field-line" placeholder="{{ __('site.contact.name_ph') }}">
                                        @error('name') <div class="field-error">{{ $message }}</div> @enderror
                                    </div>
                                    <div>
                                        <label class="field-label">
                                            @lang('site.contact.company')
                                            <span class="text-ink-600">@lang('site.contact.company_opt')</span>
                                        </label>
                                        <input type="text" name="company" value="{{ old('company') }}"
                                               class="field-line" placeholder="{{ __('site.contact.company_ph') }}">
                                    </div>
                                </div>

                                <div>
                                    <label class="field-label">@lang('site.contact.email')</label>
                                    <input type="email" name="email" required value="{{ old('email') }}"
                                           class="field-line" placeholder="{{ __('site.contact.email_ph') }}">
                                    @error('email') <div class="field-error">{{ $message }}</div> @enderror
                                </div>

                                <div x-show="kind === 'consultoria' || kind === 'parceria'">
                                    <label class="field-label">@lang('site.contact.budget')</label>
                                    <div class="flex flex-wrap gap-2 mt-3">
                                        @foreach($budget as $b)
                                            <label class="chip-sm cursor-pointer">
                                                <input type="radio" name="budget" value="{{ $b }}" {{ old('budget') === $b ? 'checked' : '' }} class="hidden">
                                                {{ $b }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div>
                                    <label class="field-label">@lang('site.contact.message')</label>
                                    <textarea name="message" rows="6" required class="field-line resize-none"
                                              placeholder="{{ __('site.contact.message_ph') }}">{{ old('message') }}</textarea>
                                    @error('message') <div class="field-error">{{ $message }}</div> @enderror
                                </div>

                                <div class="flex items-center justify-between flex-wrap gap-4 pt-2">
                                    <span class="mono-meta">// @lang('site.common.response_time')</span>
                                    <button type="submit" class="btn btn-primary">
                                        @lang('site.contact.send')
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div x-show="sent" x-cloak class="py-12 text-center">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full mb-6 bg-amber-400/20 text-amber">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                            <h3 class="text-[1.5rem] font-medium tracking-tight">@lang('site.contact.sent_title')</h3>
                            <p class="body-text mt-3 mx-auto">@lang('site.contact.sent_body')</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="divider"></div>

    <section class="section">
        <div class="wrap">
            <x-site.eyebrow num="03" :label="__('site.contact.availability_title')"/>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="card">
                    <div class="flex items-center gap-2">
                        <span class="pulse-dot"></span>
                        <span class="mono-meta-sm text-success">@lang('site.contact.status_available')</span>
                    </div>
                    <h3 class="h-card mt-3">@lang('site.contact.consulting_title')</h3>
                    <p class="body-sm mt-2">@lang('site.contact.consulting_body')</p>
                </div>
                <div class="card">
                    <div class="flex items-center gap-2">
                        <span class="hm-cell bg-warning rounded-full" style="background:var(--warning);width:6px;height:6px;border-radius:50%;"></span>
                        <span class="mono-meta-sm text-warning">@lang('site.contact.status_limited')</span>
                    </div>
                    <h3 class="h-card mt-3">@lang('site.contact.office_hours_title')</h3>
                    <p class="body-sm mt-2">@lang('site.contact.office_hours_body')</p>
                </div>
                <div class="card">
                    <div class="flex items-center gap-2">
                        <span class="pulse-dot"></span>
                        <span class="mono-meta-sm text-success">@lang('site.contact.status_open')</span>
                    </div>
                    <h3 class="h-card mt-3">@lang('site.contact.oss_title')</h3>
                    <p class="body-sm mt-2">@lang('site.contact.oss_body')</p>
                </div>
            </div>
        </div>
    </section>

</x-site.layouts.app>

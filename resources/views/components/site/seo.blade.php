@props([
    'title' => null,
    'description' => null,
    'breadcrumbs' => null,
])

@php
    $locale = app()->getLocale();
    $description = $description ?: __('site.seo.default_description');
    $fullTitle = ($title ? $title . ' · ' : '') . config('app.name');
    $canonical = url()->current();
    $ogImage = \Illuminate\Support\Facades\Vite::asset('resources/images/github-og-' . \App\Support\LocaleSupport::short() . '.png');

    $routeName = request()->route()?->getName();
    $routeParams = request()->route()?->parameters() ?? [];

    // hreflang code (URL segment) => hreflang attribute value
    $hreflangs = ['pt' => 'pt-BR', 'en' => 'en', 'es' => 'es'];

    $person = [
        '@context' => 'https://schema.org',
        '@type' => 'Person',
        'name' => 'Jefferson Gonçalves',
        'url' => config('app.url'),
        'image' => $ogImage,
        'jobTitle' => 'Full Stack PHP Developer',
        'description' => __('site.seo.default_description'),
        'email' => 'mailto:' . config('site.social.email'),
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => 'Assis',
            'addressRegion' => 'SP',
            'addressCountry' => 'BR',
        ],
        'sameAs' => array_values(array_filter([
            config('site.social.github'),
            config('site.social.linkedin'),
            config('site.social.x'),
        ])),
        'knowsAbout' => ['PHP', 'Laravel', 'Filament', 'Livewire', 'TALL Stack'],
    ];

    $website = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => config('app.name'),
        'url' => config('app.url'),
        'inLanguage' => $locale,
        'author' => ['@type' => 'Person', 'name' => 'Jefferson Gonçalves'],
    ];
@endphp

<meta name="description" content="{{ $description }}"/>
<link rel="canonical" href="{{ $canonical }}"/>

@if ($routeName)
    @foreach ($hreflangs as $code => $hreflang)
        <link rel="alternate" hreflang="{{ $hreflang }}"
              href="{{ route($routeName, array_merge($routeParams, ['locale' => $code])) }}"/>
    @endforeach
    <link rel="alternate" hreflang="x-default"
          href="{{ route($routeName, array_merge($routeParams, ['locale' => 'en'])) }}"/>
@endif

<meta property="og:title" content="{{ $fullTitle }}" data-rh="true"/>
<meta property="og:description" content="{{ $description }}" data-rh="true"/>
<meta property="og:url" content="{{ $canonical }}" data-rh="true"/>
<meta property="og:locale" content="{{ $locale }}" data-rh="true"/>

<meta name="twitter:card" content="summary_large_image"/>
<meta name="twitter:title" content="{{ $fullTitle }}"/>
<meta name="twitter:description" content="{{ $description }}"/>
<meta name="twitter:image" content="{{ $ogImage }}"/>

<script type="application/ld+json">@json($person, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
<script type="application/ld+json">@json($website, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>

@if ($breadcrumbs)
    @php
        $crumbList = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($breadcrumbs)->values()->map(fn ($c, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $c['name'],
                'item' => $c['url'],
            ])->all(),
        ];
    @endphp
    <script type="application/ld+json">@json($crumbList, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
@endif

@php
    // Match the site's default-dark theme: dark unless an explicit `light`
    // cookie is set. Keeps browser chrome (mobile address bar) in sync with
    // the pre-paint bg stamped in components.site.layouts.app. The id lets the
    // header theme toggle retarget the meta live (#theme-color-meta).
    $themeColor = request()->cookie('theme') === 'light' ? '#FFFEF9' : '#0B0A09';
@endphp
{{-- Full PWA <head> rendered by jeffersongoncalves/laravel-pwa-favicon so the
     public site, error pages and the Filament panel (via filament-pwa) all emit
     identical tags from one source. --}}
@include('pwa-favicon::head', ['themeColor' => $themeColor, 'manifestUrl' => asset('/manifest.json')])

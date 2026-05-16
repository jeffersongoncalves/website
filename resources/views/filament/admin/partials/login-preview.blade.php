@php
    $featured = \App\Models\Project::query()
        ->published()
        ->featured()
        ->orderByDesc('stars')
        ->orderBy('name')
        ->take(6)
        ->get();

    $rendered = view('site.home', [
        'featured' => $featured,
        'homeStats' => \App\Support\SiteStats::homeCards(),
        'stack' => config('site.stack'),
        'contributions' => \App\Support\SiteStats::contributions(),
    ])->render();

    // Extract just the <body> contents from the rendered home page to avoid
    // a nested <html>/<head> inside the admin layout.
    if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $rendered, $m)) {
        $bodyContent = $m[1];
    } else {
        $bodyContent = $rendered;
    }
@endphp

<div x-ignore aria-hidden="true" inert class="login-preview-bg">
    {!! $bodyContent !!}
</div>

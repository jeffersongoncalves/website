<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | HTML Input Handling
    |--------------------------------------------------------------------------
    |
    | Controls how raw, inline HTML inside the markdown source is handled.
    | One of: 'allow', 'escape', 'strip'. The default is 'escape', which is
    | safe by default: raw HTML in the source (including <script>) is escaped
    | and rendered as visible text rather than live markup.
    |
    | Set this to 'allow' ONLY for fully trusted content (e.g. your own
    | READMEs or curated article bodies). With 'allow', raw HTML passes
    | through and the OUTPUT IS UNSAFE for untrusted input — you MUST then
    | run the output through an HTML sanitizer (such as
    | jeffersongoncalves/laravel-html-sanitizer) before displaying it.
    | 'strip' removes raw HTML entirely.
    |
    */

    // README + imported article bodies are rendered with raw HTML kept ('allow')
    // and then passed through jeffersongoncalves/laravel-html-sanitizer before
    // display (README in ProjectShowPage::render(), article body in the blade) —
    // the sanitizer is the XSS control, so this preserves the v1 behaviour.
    'html_input' => 'allow',

    /*
    |--------------------------------------------------------------------------
    | Allow Unsafe Links
    |--------------------------------------------------------------------------
    |
    | When false, CommonMark strips potentially unsafe link protocols such as
    | javascript:, vbscript:, file: and data: from links and images.
    |
    */

    'allow_unsafe_links' => false,

    /*
    |--------------------------------------------------------------------------
    | Heading Permalinks
    |--------------------------------------------------------------------------
    |
    | Configuration applied to the HeadingPermalink extension when it is
    | enabled (Markdown::render($markdown, headingPermalinks: true)). The
    | symbol is rendered inside each anchor and html_class is added to it.
    |
    */

    'heading_permalink' => [
        'symbol' => '#',
        'html_class' => 'md-anchor',
    ],

];

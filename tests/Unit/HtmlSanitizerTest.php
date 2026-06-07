<?php

use App\Support\HtmlSanitizer;

it('strips script tags', function () {
    expect(HtmlSanitizer::clean('<p>hi</p><script>alert(1)</script>'))
        ->not->toContain('<script>')
        ->toContain('hi');
});

it('strips inline event handlers', function () {
    $clean = HtmlSanitizer::clean('<img src="https://x.test/a.png" onerror="alert(1)">');

    expect($clean)
        ->not->toContain('onerror')
        ->toContain('src');
});

it('drops javascript: links', function () {
    expect(HtmlSanitizer::clean('<a href="javascript:alert(1)">x</a>'))
        ->not->toContain('javascript:');
});

it('strips Alpine x-* attributes (the eval/XSS vector under unsafe-eval CSP)', function () {
    $clean = HtmlSanitizer::clean('<div x-data="{a:1}" x-init="fetch(\'/x\')" @click="evil()">hi</div>');

    expect($clean)
        ->not->toContain('x-data')
        ->not->toContain('x-init')
        ->not->toContain('@click')
        ->toContain('hi');
});

it('keeps safe formatting, links and images', function () {
    $clean = HtmlSanitizer::clean(
        '<h2>Title</h2><p><a href="https://example.test">link</a></p>'
        .'<pre><code>code</code></pre><table><tr><td>cell</td></tr></table>'
        .'<img src="https://example.test/a.png" alt="a">'
    );

    expect($clean)
        ->toContain('<h2>')
        ->toContain('href="https://example.test"')
        ->toContain('<code>')
        ->toContain('<td>')
        ->toContain('src="https://example.test/a.png"');
});

<?php

use App\Support\HtmlSanitizer;
use App\Support\Markdown;

it('syntax-highlights fenced code blocks', function () {
    $html = Markdown::render("```php\n<?php echo 'hi';\n```");

    expect($html)
        ->toContain('<pre')
        ->toContain('hl-');
});

it('keeps highlight token classes through sanitization', function () {
    $html = HtmlSanitizer::clean(Markdown::render("```php\necho 1 + 2;\n```"));

    expect($html)->toContain('hl-');
});

it('renders heading permalinks only when requested', function () {
    expect(Markdown::render('# Title', headingPermalinks: true))->toContain('md-anchor')
        ->and(Markdown::render('# Title'))->not->toContain('md-anchor');
});

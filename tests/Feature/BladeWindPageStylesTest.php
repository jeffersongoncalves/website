<?php

declare(strict_types=1);

use Daikazu\BladeWind\Pages\PageStyleStore;
use Daikazu\BladeWind\Pages\StylesDirective;
use Daikazu\BladeWind\Testing\AssertsPageStyles;
use Daikazu\BladeWind\Testing\PageExpectation;

uses(AssertsPageStyles::class);

beforeEach(fn () => app(PageStyleStore::class)->clear());

it('serves {0} per-page styles instead of the full stylesheet', function (string $path) {
    $this->assertPageStyles($path, new PageExpectation(
        framework: 'tailwind4',
        // laravel-gtag's script.blade.php trips the Forte lexer (@php inside <script>).
        // The view carries no classes, so the page's class set is unaffected.
        diagnostics: ['BW1008'],
    ));
})->with([
    '/en',
    '/en/about',
    '/en/projects',
    '/en/articles',
    '/en/links',
    '/en/open-source',
    '/en/stack',
    '/en/sponsors',
]);

it('inlines the offline page CSS so the precached HTML carries it', function () {
    $result = $this->assertPageStyles('/offline', new PageExpectation(
        framework: 'tailwind4',
        diagnostics: ['BW1008'],
    ));

    expect($result->delivery)->toBe('inline');
});

it('stores the page-styles links in the full-page cache, not the placeholder links', function () {
    // A cache hit renders no views, so BladeWind rewriting it would build the class set
    // from the HTML alone and drop classes Livewire adds on update. The cache must hold
    // the response BladeWind already rewrote on the miss.
    config(['page-cache.enabled' => true]);

    $miss = $this->get('/en/stack')->assertOk()->getContent();
    $hit = $this->get('/en/stack')->assertOk()->getContent();

    preg_match('~bw-page-[^"\']+\.css~', (string) $miss, $missPage);

    expect($missPage)->not->toBeEmpty()
        ->and($hit)->toContain($missPage[0])
        ->and($hit)->not->toContain(StylesDirective::MARKER);
});

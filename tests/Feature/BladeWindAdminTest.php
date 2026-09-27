<?php

declare(strict_types=1);

use App\Models\Admin;
use Daikazu\BladeWind\Pages\PageStyleStore;
use Daikazu\BladeWind\Testing\AssertsPageStyles;
use Daikazu\BladeWind\Testing\PageExpectation;
use JeffersonGoncalves\Filament\BladeWind\Http\Middleware\ApplyBladeWind;
use Livewire\Livewire;

uses(AssertsPageStyles::class);

beforeEach(fn () => app(PageStyleStore::class)->clear());

// Filament's vendor views carry dynamic @include targets (BW1001), unenumerable Alpine
// bindings (BW2002) and non-string @class items (BW2004); laravel-gtag trips the parser (BW1008).
it('serves {0} per-page panel styles, fi-* components included', function (string $uri, array $diagnostics) {
    if ($uri !== '/admin/login') {
        $this->actingAs(Admin::factory()->create(), 'admin');
    }

    $result = $this->assertPageStyles($uri, new PageExpectation(framework: 'filament', diagnostics: $diagnostics));

    // The shared root holds no fi-* rule; the page file carries what the page uses.
    expect($result->rootCss)->not->toContain('.fi-btn{')
        ->and(strlen($result->rootCss) + strlen($result->pageCss))->toBeLessThan((int) (strlen($result->fullCss) * 0.7));
})->with([
    ['/admin/login', ['BW1001', 'BW1008', 'BW2004', 'BW6005']],
    ['/admin', ['BW1001', 'BW1008', 'BW2004', 'BW6005']],
    ['/admin/projects/create', ['BW1001', 'BW1008', 'BW2004', 'BW6005']],
    ['/admin/admins', ['BW1001', 'BW1008', 'BW2002', 'BW2004', 'BW6005']],
]);

it('streams the CSS an action modal needs to the Livewire update that opens it', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $target = Admin::factory()->create();

    $html = (string) $this->get('/admin/admins')->assertOk()->getContent();

    preg_match('~<meta name="filament-bladewind" content="([0-9a-f]{24})"~', $html, $meta);
    preg_match('~<link[^>]+href="([^"]*bw-page-[^"]+\.css)"~', $html, $pageLink);
    preg_match_all('~wire:snapshot="([^"]+)"~', $html, $snapshots);

    $snapshot = collect($snapshots[1])
        ->map(fn (string $raw): string => html_entity_decode($raw))
        ->first(fn (string $json): bool => str_contains($json, 'ListAdmins'));

    expect($meta)->not->toBeEmpty()->and($pageLink)->not->toBeEmpty()->and($snapshot)->not->toBeNull();

    $update = fn () => $this->withHeaders(['X-Livewire' => '1', ApplyBladeWind::HEADER => $meta[1]])
        ->postJson(Livewire::getUpdateUri(), ['components' => [[
            'snapshot' => $snapshot,
            'updates' => [],
            'calls' => [['path' => '', 'method' => 'mountAction', 'params' => ['delete', [], ['recordKey' => (string) $target->getKey(), 'table' => true]]]],
        ]]])
        ->assertOk()
        ->json('components.0.effects');

    $effects = $update();
    $css = fn (string $link): string => (string) file_get_contents(public_path(parse_url($link, PHP_URL_PATH)));
    $pageCss = $css($pageLink[1]);
    preg_match('~<link[^>]+href="([^"]*bw-root-[^"]+\.css)"~', $html, $rootLink);
    $loaded = $css($rootLink[1]).$pageCss;
    $fullCss = (string) file_get_contents(public_path('build/'.json_decode((string) file_get_contents(public_path('build/manifest.json')), true)['resources/css/filament/admin/theme.css']['file']));

    // Filament 5 sends action modals as a Livewire 4 partial, not in the component's html.
    $modal = (string) ($effects['partials']['action-modals'] ?? '');
    $selector = fn (string $class): string => '.'.addcslashes($class, ':/.[]()%#!,');

    // Every class the modal renders that the theme styles but the loaded page did not.
    preg_match_all('~\bclass="([^"]*)"~', $modal, $classes);
    $missing = collect(preg_split('~\s+~', implode(' ', $classes[1]), -1, PREG_SPLIT_NO_EMPTY))
        ->unique()
        ->filter(fn (string $class): bool => str_contains($fullCss, $selector($class)) && ! str_contains($loaded, $selector($class)))
        ->values();

    expect($modal)->toContain('fi-modal');

    foreach ($missing as $class) {
        expect($effects['bladewind'] ?? '')->toContain($selector($class));
    }

    // A re-emitted rule must not jump ahead of page rules that override it outside the modal:
    // the topbar hides its mobile close button (an .fi-icon-btn) on desktop.
    $delta = (string) ($effects['bladewind'] ?? '');
    $iconBtn = strpos($delta, '.fi-icon-btn{');

    if ($iconBtn !== false) {
        expect(strpos($delta, '.fi-topbar-close-sidebar-btn{display:none}'))->toBeGreaterThan($iconBtn);
    }

    // The same modal again: the page now covers it, so nothing more is streamed.
    expect($update())->not->toHaveKey('bladewind');
});

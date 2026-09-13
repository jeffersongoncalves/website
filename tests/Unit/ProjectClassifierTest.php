<?php

declare(strict_types=1);

use App\Support\ProjectClassifier;

it('promotes awesome repos by name even when other topics match', function (): void {
    expect(ProjectClassifier::specificFromTopics(['laravel', 'php'], 'awesome-laravel'))
        ->toBe('awesome_list');
});

it('detects awesome lists from the topic', function (): void {
    expect(ProjectClassifier::specificFromTopics(['awesome-list'], 'curated-stuff'))
        ->toBe('awesome_list');
});

it('maps platform topics to mobile_library', function (): void {
    expect(ProjectClassifier::specificFromTopics(['android', 'kotlin'], 'some-sdk'))
        ->toBe('mobile_library');
});

it('maps learning topics to learning_resource', function (): void {
    expect(ProjectClassifier::specificFromTopics(['roadmap'], 'developer-roadmap'))
        ->toBe('learning_resource');
});

it('maps database topics to database', function (): void {
    expect(ProjectClassifier::specificFromTopics(['postgresql', 'docker'], 'pg-thing'))
        ->toBe('database');
});

it('maps css-framework topic to css_framework', function (): void {
    expect(ProjectClassifier::specificFromTopics(['css-framework'], 'some-css'))
        ->toBe('css_framework');
});

it('is case insensitive on topics', function (): void {
    expect(ProjectClassifier::specificFromTopics(['Android'], 'X'))
        ->toBe('mobile_library');
});

it('returns null when no topic or name signal matches', function (): void {
    expect(ProjectClassifier::specificFromTopics(['cli', 'utility'], 'some-tool'))
        ->toBeNull();
});

it('does not promote a bare laravel topic to laravel_package', function (): void {
    // Multi-framework JS/TS projects (e.g. shadcn-ui/ui) tag `laravel`
    // because they support it, not because they're a PHP package. This
    // topic-only path can't see a composer.json, so it must not claim them.
    expect(ProjectClassifier::specificFromTopics(['react', 'nextjs', 'laravel', 'tailwindcss'], 'ui'))
        ->toBeNull();
});

it('classifies a filament plugin from the composer type', function (): void {
    expect(ProjectClassifier::category(['type' => 'filament-plugin', 'name' => 'acme/thing'], ['name' => 'thing']))
        ->toBe('filament_plugin');
});

it('classifies a filament plugin from the filament-plugin repo topic', function (): void {
    expect(ProjectClassifier::category(null, ['name' => 'thing', 'topics' => ['filament-plugin']]))
        ->toBe('filament_plugin');
});

it('classifies a filament plugin from the filament topic plus a filament/* require', function (): void {
    expect(ProjectClassifier::category(
        ['require' => ['filament/support' => '^3.0']],
        ['name' => 'thing', 'topics' => ['filament']],
    ))->toBe('filament_plugin');
});

it('does not classify a bare filament topic without a filament/* require as a plugin', function (): void {
    expect(ProjectClassifier::category(
        ['require' => ['laravel/framework' => '^11.0']],
        ['name' => 'thing', 'topics' => ['filament']],
    ))->toBe('laravel_package');
});

it('classifies a laravel-zero CLI from the require', function (): void {
    expect(ProjectClassifier::category(['require' => ['laravel-zero/framework' => '^11.0']], ['name' => 'cli']))
        ->toBe('laravel_zero_cli');
});

it('classifies a cakephp package from the composer vendor', function (): void {
    expect(ProjectClassifier::category(['name' => 'cakephp/thing'], ['name' => 'thing']))
        ->toBe('cakephp_package');
});

it('classifies a cakephp package from the cakephp topic when the vendor differs', function (): void {
    expect(ProjectClassifier::category(['name' => 'jeffersongoncalves/thing'], ['name' => 'thing', 'topics' => ['cakephp']]))
        ->toBe('cakephp_package');
});

it('classifies a livewire package from the topic plus require', function (): void {
    expect(ProjectClassifier::category(
        ['require' => ['livewire/livewire' => '^3.0']],
        ['name' => 'thing', 'topics' => ['livewire']],
    ))->toBe('livewire_package');
});

it('does not classify a livewire topic alone (no require) as a livewire package', function (): void {
    expect(ProjectClassifier::category(null, ['name' => 'thing', 'topics' => ['livewire']]))
        ->toBe('tool');
});

it('classifies a starter kit from the topic', function (): void {
    expect(ProjectClassifier::category(null, ['name' => 'kit', 'topics' => ['starter-kit']]))
        ->toBe('starter_kit');
});

it('classifies a laravel package from the topic when a composer.json exists', function (): void {
    expect(ProjectClassifier::category(['name' => 'acme/thing'], ['name' => 'thing', 'topics' => ['laravel']]))
        ->toBe('laravel_package');
});

it('does not classify a bare laravel topic as a package without a composer.json', function (): void {
    expect(ProjectClassifier::category(null, ['name' => 'ui', 'topics' => ['laravel', 'react']]))
        ->toBe('tool');
});

it('classifies a framework from the topic', function (): void {
    expect(ProjectClassifier::category(null, ['name' => 'thing', 'topics' => ['framework']]))
        ->toBe('framework');
});

it('classifies a docker project from a broader self-hosted topic', function (): void {
    expect(ProjectClassifier::category(null, ['name' => 'app', 'topics' => ['selfhosted']]))
        ->toBe('docker');
});

it('classifies a docker project from the compose flag, even without docker topics', function (): void {
    expect(ProjectClassifier::category(null, ['name' => 'selfhosted-app'], hasDockerCompose: true))
        ->toBe('docker');
});

it('lets database topics win over a docker compose file', function (): void {
    expect(ProjectClassifier::category(null, ['name' => 'pg', 'topics' => ['postgresql']], hasDockerCompose: true))
        ->toBe('database');
});

it('classifies an awesome list from the category() method\'s own name/topic check', function (): void {
    expect(ProjectClassifier::category(null, ['name' => 'awesome-things']))->toBe('awesome_list');
    expect(ProjectClassifier::category(null, ['name' => 'curated', 'topics' => ['awesome-list']]))->toBe('awesome_list');
});

it('classifies a css framework from the category() method\'s own topic check', function (): void {
    expect(ProjectClassifier::category(null, ['name' => 'styles', 'topics' => ['css-framework']]))
        ->toBe('css_framework');
});

it('classifies a mobile library from the category() method\'s own topic check', function (): void {
    expect(ProjectClassifier::category(null, ['name' => 'sdk', 'topics' => ['android']]))
        ->toBe('mobile_library');
});

it('classifies a learning resource from the category() method\'s own topic check', function (): void {
    expect(ProjectClassifier::category(null, ['name' => 'guide', 'topics' => ['roadmap']]))
        ->toBe('learning_resource');
});

it('falls back to tool when no signal matches', function (): void {
    expect(ProjectClassifier::category(null, ['name' => 'random-cli']))->toBe('tool');
});

it('detects the tech stack from composer and npm dependencies', function (): void {
    $stack = ProjectClassifier::stack(
        ['require' => ['laravel/framework' => '*', 'filament/filament' => '*', 'livewire/livewire' => '*']],
        ['dependencies' => ['tailwindcss' => '*'], 'devDependencies' => ['alpinejs' => '*']],
    );

    expect($stack)->toBe(['Laravel', 'Filament', 'Livewire', 'Tailwind', 'Alpine.js']);
});

it('detects Vue and React in the stack', function (): void {
    expect(ProjectClassifier::stack(null, ['dependencies' => ['vue' => '*']]))->toBe(['Vue']);
    expect(ProjectClassifier::stack(null, ['dependencies' => ['react' => '*']]))->toBe(['React']);
});

it('returns an empty stack when neither composer nor package data is given', function (): void {
    expect(ProjectClassifier::stack(null, null))->toBe([]);
});

it('parses supported filament versions for a plugin and ignores out-of-range majors', function (): void {
    expect(ProjectClassifier::versions(['require' => ['filament/filament' => '^3.0|^4.0|^5.0']], [], 'filament_plugin'))
        ->toBe(['v3', 'v4', 'v5']);
});

it('returns no versions for a non-filament-plugin category', function (): void {
    expect(ProjectClassifier::versions(['require' => ['filament/filament' => '^3.0']], [], 'laravel_package'))
        ->toBe([]);
});

it('excludes a major outside the plausible 3-9 filament range', function (): void {
    // Guards against a malformed/future constraint fabricating a bogus
    // version entry (e.g. a typo'd "^30.0" or a pre-v3 "^1.0" leftover).
    expect(ProjectClassifier::versions(['require' => ['filament/filament' => '^1.0']], [], 'filament_plugin'))
        ->toBe([]);
    expect(ProjectClassifier::versions(['require' => ['filament/filament' => '^10.0']], [], 'filament_plugin'))
        ->toBe([]);
});

it('returns no versions when the filament/filament require is missing entirely', function (): void {
    expect(ProjectClassifier::versions(['require' => ['laravel/framework' => '*']], [], 'filament_plugin'))
        ->toBe([]);
});

it('detects per-version branches from a real branch list', function (): void {
    expect(ProjectClassifier::hasVersionBranches(['main', '1.x', '2.x'], 'filament_plugin'))->toBeTrue();
    expect(ProjectClassifier::hasVersionBranches(['main', 'develop'], 'filament_plugin'))->toBeFalse();
});

it('never reports version branches for a non-filament-plugin category', function (): void {
    expect(ProjectClassifier::hasVersionBranches(['1.x', '2.x'], 'laravel_package'))->toBeFalse();
});

it('does not mistake a minor version digit for a second major', function (): void {
    // "^5.3" contains a "3" too — matching every digit in the string (the
    // old approach) fabricated a phantom v3 alongside the real v5. This is
    // the exact composer.json shape (default-branch constraint from a
    // single Filament major) confirmed live on jeffersongoncalves/filament-ban.
    expect(ProjectClassifier::versions(['require' => ['filament/filament' => '^5.3']], [], 'filament_plugin'))
        ->toBe(['v5']);

    expect(ProjectClassifier::versions(['require' => ['filament/filament' => '^4.8']], [], 'filament_plugin'))
        ->toBe(['v4']);
});

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

it('classifies a laravel package from the composer require', function (): void {
    expect(ProjectClassifier::category(['require' => ['laravel/framework' => '^11.0']], ['name' => 'pkg']))
        ->toBe('laravel_package');
});

it('classifies a docker project from the compose flag, even without docker topics', function (): void {
    expect(ProjectClassifier::category(null, ['name' => 'selfhosted-app'], hasDockerCompose: true))
        ->toBe('docker');
});

it('lets database topics win over a docker compose file', function (): void {
    expect(ProjectClassifier::category(null, ['name' => 'pg', 'topics' => ['postgresql']], hasDockerCompose: true))
        ->toBe('database');
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

it('parses supported filament versions for a plugin and ignores out-of-range majors', function (): void {
    expect(ProjectClassifier::versions(['require' => ['filament/filament' => '^3.0|^4.0|^5.0']], [], 'filament_plugin'))
        ->toBe(['v3', 'v4', 'v5']);
});

it('returns no versions for a non-filament-plugin category', function (): void {
    expect(ProjectClassifier::versions(['require' => ['filament/filament' => '^3.0']], [], 'laravel_package'))
        ->toBe([]);
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

<?php

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

<?php

use App\Support\ProjectTopics;

it('normalizes, slugifies and lowercases', function (): void {
    expect(ProjectTopics::normalize(['Laravel', 'Unit Testing', '  PHP  ']))
        ->toBe(['laravel', 'unit-testing', 'php']);
});

it('merges multiple lists and dedupes', function (): void {
    expect(ProjectTopics::normalize(['filament', 'laravel'], ['Laravel', 'php']))
        ->toBe(['filament', 'laravel', 'php']);
});

it('drops non-strings, empties and overly long values', function (): void {
    expect(ProjectTopics::normalize(['ok', '', 123, null, str_repeat('x', 60)]))
        ->toBe(['ok']);
});

it('caps at 20 topics', function (): void {
    $many = array_map(fn (int $i): string => "topic-{$i}", range(1, 40));

    expect(ProjectTopics::normalize($many))->toHaveCount(20);
});

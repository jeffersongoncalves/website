<?php

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Jobs\TranslateProjectTitleJob;
use App\Models\Project;
use Illuminate\Support\Facades\Http;

it('translates the English title into pt-BR and es when the locales are empty', function (): void {
    Http::fake([
        'translate.googleapis.com/translate_a/single?*tl=pt-BR*' => Http::response([
            [['Olá mundo', 'Hello world', null, null, 1]],
            null,
            'en',
        ], 200),
        'translate.googleapis.com/translate_a/single?*tl=es*' => Http::response([
            [['Hola mundo', 'Hello world', null, null, 1]],
            null,
            'en',
        ], 200),
    ]);

    $project = Project::query()->create([
        'slug' => 'translate-test',
        'name' => 'Translate Test',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'title' => ['en' => 'Hello world'],
    ]);

    (new TranslateProjectTitleJob($project))->handle();

    $project->refresh();

    expect($project->getTranslation('title', 'pt', false))->toBe('Olá mundo');
    expect($project->getTranslation('title', 'es', false))->toBe('Hola mundo');
});

it('skips locales that already hold a different translation', function (): void {
    Http::fake([
        'translate.googleapis.com/translate_a/single?*tl=es*' => Http::response([
            [['Hola mundo', 'Hello world', null, null, 1]],
            null,
            'en',
        ], 200),
    ]);

    $project = Project::query()->create([
        'slug' => 'translate-skip',
        'name' => 'Translate Skip',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'title' => [
            'en' => 'Hello world',
            'pt' => 'Texto traduzido manualmente',
        ],
    ]);

    (new TranslateProjectTitleJob($project))->handle();

    $project->refresh();

    expect($project->getTranslation('title', 'pt', false))->toBe('Texto traduzido manualmente');
    expect($project->getTranslation('title', 'es', false))->toBe('Hola mundo');
});

it('does nothing when the English title is empty', function (): void {
    Http::fake(fn () => throw new RuntimeException('should not hit the network'));

    $project = Project::query()->create([
        'slug' => 'translate-empty',
        'name' => 'Translate Empty',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'title' => [],
    ]);

    (new TranslateProjectTitleJob($project))->handle();

    $project->refresh();

    expect($project->getTranslation('title', 'pt', false))->toBe('');
    expect($project->getTranslation('title', 'es', false))->toBe('');
});

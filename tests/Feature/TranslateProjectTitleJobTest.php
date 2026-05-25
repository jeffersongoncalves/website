<?php

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Jobs\TranslateProjectTitleJob;
use App\Models\Project;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;

it('translates the English title into pt-BR and es when the locales are empty', function (): void {
    Prism::fake([
        TextResponseFake::make()->withText('Olá mundo'),
        TextResponseFake::make()->withText('Hola mundo'),
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
    Prism::fake([
        TextResponseFake::make()->withText('Hola mundo'),
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
    Prism::fake([
        TextResponseFake::make()->withText('should not appear'),
    ]);

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

it('strips wrapping quotes from the model output', function (): void {
    Prism::fake([
        TextResponseFake::make()->withText('"Olá mundo"'),
        TextResponseFake::make()->withText("'Hola mundo'"),
    ]);

    $project = Project::query()->create([
        'slug' => 'translate-quotes',
        'name' => 'Translate Quotes',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'title' => ['en' => 'Hello world'],
    ]);

    (new TranslateProjectTitleJob($project))->handle();

    $project->refresh();

    expect($project->getTranslation('title', 'pt', false))->toBe('Olá mundo');
    expect($project->getTranslation('title', 'es', false))->toBe('Hola mundo');
});

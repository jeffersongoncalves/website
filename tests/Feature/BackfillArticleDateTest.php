<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Jobs\BackfillArticleDateJob;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

function articleRow(string $publishedAt): Project
{
    return Project::query()->create([
        'name' => 'Some Article',
        'slug' => 'article-some-article',
        'category' => ProjectCategory::Article,
        'status' => ProjectStatus::Published,
        'docs_url' => 'https://blog.test/some-article',
        'published_at' => $publishedAt,
    ]);
}

it('corrects published_at to the source article real date', function () {
    Http::swap(new Factory);
    Http::fake([
        'blog.test/*' => Http::response(
            '<html><head><meta property="og:title" content="Some Article">'
            .'<meta property="article:published_time" content="2024-03-15T10:00:00Z"></head></html>',
            200,
        ),
    ]);

    $project = articleRow('2026-06-02 08:00:00'); // wrong import date

    (new BackfillArticleDateJob($project->id))->handle();

    expect($project->fresh()->published_at->format('Y-m-d'))->toBe('2024-03-15');
});

it('leaves the date untouched when the source ships no parseable date', function () {
    Http::swap(new Factory);
    Http::fake([
        'blog.test/*' => Http::response('<html><head><meta property="og:title" content="No Date"></head></html>', 200),
    ]);

    $project = articleRow('2026-06-02 08:00:00');

    (new BackfillArticleDateJob($project->id))->handle();

    expect($project->fresh()->published_at->format('Y-m-d'))->toBe('2026-06-02');
});

it('does nothing when the project no longer exists', function () {
    (new BackfillArticleDateJob(999999))->handle();
})->throwsNoExceptions();

it('does nothing when the project has no docs_url', function () {
    $project = Project::query()->create([
        'name' => 'No Docs',
        'slug' => 'article-no-docs',
        'category' => ProjectCategory::Article,
        'status' => ProjectStatus::Published,
        'docs_url' => null,
        'published_at' => '2026-06-02 08:00:00',
    ]);

    (new BackfillArticleDateJob($project->id))->handle();

    expect($project->fresh()->published_at->format('Y-m-d'))->toBe('2026-06-02');
});

it('skips the write when the stored date already matches within a day', function () {
    Http::swap(new Factory);
    Http::fake([
        'blog.test/*' => Http::response(
            '<html><head><meta property="article:published_time" content="2024-03-15T10:00:00Z"></head></html>',
            200,
        ),
    ]);

    $project = articleRow('2024-03-15 10:30:00'); // within a day of the real date

    $before = $project->updated_at;
    (new BackfillArticleDateJob($project->id))->handle();

    expect($project->fresh()->updated_at)->toEqual($before);
});

it('guards against overlapping runs for the same project', function () {
    $middleware = (new BackfillArticleDateJob(1))->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class);
});

it('logs context when BackfillArticleDateJob fails', function () {
    Log::shouldReceive('error')
        ->once()
        ->with('BackfillArticleDateJob failed', Mockery::on(fn ($ctx) => $ctx['project_id'] === 42 && $ctx['error'] === 'boom'));

    (new BackfillArticleDateJob(42))->failed(new RuntimeException('boom'));
});

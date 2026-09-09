<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves a plain-text llms.txt map with pages, authored projects and articles', function () {
    Project::query()->create([
        'name' => 'filament-gtag',
        'slug' => 'jeffersongoncalves-filament-gtag',
        'title' => ['en' => 'Google Analytics gtag for Filament'],
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/filament-gtag',
        'stars' => 42,
        'published_at' => now(),
    ]);

    Project::query()->create([
        'name' => 'Some Article',
        'slug' => 'article-some-article',
        'title' => ['en' => 'A deep dive into queues'],
        'category' => ProjectCategory::Article,
        'status' => ProjectStatus::Published,
        'docs_url' => 'https://blog.test/some-article',
        'published_at' => now(),
    ]);

    $this->artisan('llms:generate')->assertSuccessful();

    $this->get('/llms.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertSee('# Jefferson Gonçalves', false)
        ->assertSee('## Pages', false)
        ->assertSee('## Open Source Projects', false)
        ->assertSee('[filament-gtag](', false)
        ->assertSee('Google Analytics gtag for Filament', false)
        ->assertSee('## Articles', false)
        ->assertSee('[Some Article](', false);
});

it('excludes a third-party (non-authored) repo from the project list', function () {
    Project::query()->create([
        'name' => 'someone-else-repo',
        'slug' => 'someoneelse-repo',
        'category' => ProjectCategory::LaravelPackage,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/someoneelse/repo',
        'published_at' => now(),
    ]);

    $this->artisan('llms:generate')->assertSuccessful();

    $this->get('/llms.txt')
        ->assertOk()
        ->assertDontSee('someone-else-repo', false);
});

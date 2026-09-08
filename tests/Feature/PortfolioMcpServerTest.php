<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Mcp\Resources\SiteMapResource;
use App\Mcp\Servers\PortfolioServer;
use App\Mcp\Tools\GetPageTool;
use App\Mcp\Tools\SearchProjectsTool;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('finds a published project by name via search_projects', function () {
    Project::query()->create([
        'name' => 'filament-gtag',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/filament-gtag',
        'published_at' => now(),
    ]);

    PortfolioServer::tool(SearchProjectsTool::class, ['query' => 'gtag'])
        ->assertOk()
        ->assertSee('filament-gtag')
        ->assertSee(route('projects.show', 'jeffersongoncalves-filament-gtag'));
});

it('excludes unpublished projects from search_projects', function () {
    Project::query()->create([
        'name' => 'hidden-project',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Draft,
        'github_url' => 'https://github.com/jeffersongoncalves/hidden-project',
    ]);

    PortfolioServer::tool(SearchProjectsTool::class, ['query' => 'hidden'])
        ->assertOk()
        ->assertSee('No published pages matched');
});

it('returns metadata and a readme excerpt for get_page', function () {
    // Http::fake for the README endpoint is already set up globally in
    // tests/Pest.php's Feature beforeEach ('# README' fixture) — the first
    // matching stub wins, so a per-test override here would be shadowed.
    Storage::fake('github');

    $project = Project::query()->create([
        'name' => 'filament-gtag',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/filament-gtag',
        'published_at' => now(),
    ]);

    PortfolioServer::tool(GetPageTool::class, ['slug' => $project->slug])
        ->assertOk()
        ->assertSee('filament-gtag')
        ->assertSee('## README')
        ->assertSee('README');
});

it('errors on an unknown slug for get_page', function () {
    PortfolioServer::tool(GetPageTool::class, ['slug' => 'does-not-exist'])
        ->assertHasErrors();
});

it('serves the llms.txt body as the site map resource', function () {
    PortfolioServer::resource(SiteMapResource::class)
        ->assertOk()
        ->assertSee('# Jefferson Gonçalves');
});

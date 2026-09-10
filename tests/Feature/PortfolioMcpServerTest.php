<?php

declare(strict_types=1);

use App\Enums\PackageType;
use App\Enums\ProjectCategory;
use App\Enums\ProjectLanguage;
use App\Enums\ProjectStatus;
use App\Mcp\Resources\SiteMapResource;
use App\Mcp\Servers\PortfolioServer;
use App\Mcp\Tools\GetPageTool;
use App\Mcp\Tools\GetSiteStatsTool;
use App\Mcp\Tools\SearchProjectsTool;
use App\Models\Project;
use App\Support\SiteStats;
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

it('finds a published project by topic or stack via search_projects', function () {
    Project::query()->create([
        'name' => 'filament-gtag',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/filament-gtag',
        'published_at' => now(),
        'topics' => ['analytics', 'google-tag-manager'],
        'stack' => ['Laravel', 'Filament'],
    ]);

    PortfolioServer::tool(SearchProjectsTool::class, ['query' => 'google-tag-manager'])
        ->assertOk()
        ->assertSee('filament-gtag');

    PortfolioServer::tool(SearchProjectsTool::class, ['query' => 'Filament'])
        ->assertOk()
        ->assertSee('filament-gtag');
});

it('returns the requested locale\'s title in search_projects, falling back to English', function () {
    Project::query()->create([
        'name' => 'filament-gtag',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/filament-gtag',
        'published_at' => now(),
        'title' => ['en' => 'Google Tag Manager for Filament', 'pt_BR' => 'Google Tag Manager para Filament'],
    ]);

    PortfolioServer::tool(SearchProjectsTool::class, ['query' => 'gtag', 'locale' => 'pt_BR'])
        ->assertOk()
        ->assertSee('Google Tag Manager para Filament');

    PortfolioServer::tool(SearchProjectsTool::class, ['query' => 'gtag', 'locale' => 'es'])
        ->assertOk()
        ->assertSee('Google Tag Manager for Filament');
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

it('includes license, downloads, package type, language and versions in get_page', function () {
    Storage::fake('github');

    $project = Project::query()->create([
        'name' => 'filament-gtag',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/filament-gtag',
        'published_at' => now(),
        'license' => 'MIT',
        'downloads' => 12345,
        'downloads_label' => '12.3k',
        'package_type' => PackageType::Composer,
        'language' => ProjectLanguage::Php,
        'versions' => ['v4', 'v3', 'v5'],
    ]);

    PortfolioServer::tool(GetPageTool::class, ['slug' => $project->slug])
        ->assertOk()
        ->assertSee('License: MIT')
        ->assertSee('Downloads: 12.3k')
        ->assertSee('Package type: composer')
        ->assertSee('Primary language: PHP')
        ->assertSee('Supported Filament versions: v3, v4, v5');
});

it('returns the requested locale\'s title in get_page, falling back to English', function () {
    Storage::fake('github');

    $project = Project::query()->create([
        'name' => 'filament-gtag',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/filament-gtag',
        'published_at' => now(),
        'title' => ['en' => 'Google Tag Manager for Filament', 'pt_BR' => 'Google Tag Manager para Filament'],
    ]);

    PortfolioServer::tool(GetPageTool::class, ['slug' => $project->slug, 'locale' => 'pt_BR'])
        ->assertOk()
        ->assertSee('Google Tag Manager para Filament');

    PortfolioServer::tool(GetPageTool::class, ['slug' => $project->slug, 'locale' => 'es'])
        ->assertOk()
        ->assertSee('Google Tag Manager for Filament');
});

it('errors on an unknown slug for get_page', function () {
    PortfolioServer::tool(GetPageTool::class, ['slug' => 'does-not-exist'])
        ->assertHasErrors();
});

it('returns aggregate stats via get_site_stats', function () {
    Project::query()->create([
        'name' => 'filament-gtag',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/filament-gtag',
        'published_at' => now(),
        'stars' => 42,
    ]);

    SiteStats::refreshProjectDerived();

    PortfolioServer::tool(GetSiteStatsTool::class)
        ->assertOk()
        ->assertSee('## Packages by category')
        ->assertSee('Filament plugins: 1')
        ->assertSee('Total stars: 42')
        ->assertSee('## Support this work')
        ->assertSee(config('site.social.sponsors'));
});

it('serves the llms.txt body as the site map resource', function () {
    $this->artisan('llms:generate')->assertSuccessful();

    PortfolioServer::resource(SiteMapResource::class)
        ->assertOk()
        ->assertSee('# Jefferson Gonçalves');
});

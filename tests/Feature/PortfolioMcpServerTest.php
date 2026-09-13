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
use Illuminate\Support\Facades\Http;
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

it('errors when the slug argument is blank for get_page', function () {
    PortfolioServer::tool(GetPageTool::class, ['slug' => '  '])
        ->assertHasErrors();
});

it('includes stars, stack and topics lines in get_page when present', function () {
    Storage::fake('github');

    $project = Project::query()->create([
        'name' => 'filament-gtag',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/filament-gtag',
        'published_at' => now(),
        'stars' => 42,
        'stack' => ['Laravel', 'Filament'],
        'topics' => ['analytics', 'gtag'],
    ]);

    PortfolioServer::tool(GetPageTool::class, ['slug' => $project->slug])
        ->assertOk()
        ->assertSee('Stars: 42')
        ->assertSee('Stack: Laravel, Filament')
        ->assertSee('Topics: analytics, gtag');
});

it('reads the readme excerpt from npm when the project has no github_url', function () {
    Http::fake(['registry.npmjs.org/*' => Http::response(['readme' => '# Pkg readme body'])]);

    $project = Project::query()->create([
        'name' => 'a-pkg',
        'category' => ProjectCategory::JavascriptPackage,
        'status' => ProjectStatus::Published,
        'npm_url' => 'https://www.npmjs.com/package/a-pkg',
        'published_at' => now(),
    ]);

    PortfolioServer::tool(GetPageTool::class, ['slug' => $project->slug])
        ->assertOk()
        ->assertSee('## README')
        ->assertSee('Pkg readme body');
});

it('omits the readme section for get_page when the project has neither a github_url nor an npm_url', function () {
    $project = Project::query()->create([
        'name' => 'link-only',
        'category' => ProjectCategory::Website,
        'status' => ProjectStatus::Published,
        'docs_url' => 'https://example.com',
        'published_at' => now(),
    ]);

    PortfolioServer::tool(GetPageTool::class, ['slug' => $project->slug])
        ->assertOk()
        ->assertDontSee('## README');
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

it('includes top languages and topics in get_site_stats when present', function () {
    Project::query()->create([
        'name' => 'filament-gtag',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/filament-gtag',
        'published_at' => now(),
        'language' => ProjectLanguage::Php,
        'topics' => ['analytics', 'gtag'],
    ]);

    SiteStats::refreshProjectDerived();

    PortfolioServer::tool(GetSiteStatsTool::class)
        ->assertOk()
        ->assertSee('## Top languages')
        ->assertSee('- PHP: 1')
        ->assertSee('## Top topics')
        ->assertSee('- analytics: 1');
});

it('restricts search_projects to a single section', function () {
    Project::query()->create([
        'name' => 'my-article',
        'category' => ProjectCategory::Article,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
    ]);
    Project::query()->create([
        'name' => 'my-plugin',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/my-plugin',
        'published_at' => now(),
    ]);

    PortfolioServer::tool(SearchProjectsTool::class, ['section' => 'articles'])
        ->assertOk()
        ->assertSee('my-article')
        ->assertDontSee('my-plugin');

    PortfolioServer::tool(SearchProjectsTool::class, ['section' => 'projects'])
        ->assertOk()
        ->assertSee('my-plugin')
        ->assertDontSee('my-article');
});

it('restricts search_projects to an exact category', function () {
    Project::query()->create([
        'name' => 'a-plugin',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/a-plugin',
        'published_at' => now(),
    ]);
    Project::query()->create([
        'name' => 'a-package',
        'category' => ProjectCategory::LaravelPackage,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/a-package',
        'published_at' => now(),
    ]);

    PortfolioServer::tool(SearchProjectsTool::class, ['category' => 'filament_plugin'])
        ->assertOk()
        ->assertSee('a-plugin')
        ->assertDontSee('a-package');
});

it('clamps a below-minimum search_projects limit up to 1', function () {
    // orderByDesc(stars)->orderBy(name) with equal stars ties on name asc,
    // so a limit of 1 (clamped from 0) returns only "proj-1".
    foreach (range(1, 3) as $i) {
        Project::query()->create([
            'name' => "proj-{$i}",
            'category' => ProjectCategory::Tool,
            'status' => ProjectStatus::Published,
            'github_url' => "https://github.com/jeffersongoncalves/proj-{$i}",
            'published_at' => now(),
        ]);
    }

    PortfolioServer::tool(SearchProjectsTool::class, ['limit' => 0])
        ->assertOk()
        ->assertSee('proj-1')
        ->assertDontSee('proj-2')
        ->assertDontSee('proj-3');
});

it('clamps an above-maximum search_projects limit down to 50', function () {
    // Nothing to distinguish 50 from an uncapped value with only a handful of
    // rows — this just proves an oversized limit doesn't error or get passed
    // straight through as a pagination size the DB layer might reject.
    Project::query()->create([
        'name' => 'solo-project',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/solo-project',
        'published_at' => now(),
    ]);

    PortfolioServer::tool(SearchProjectsTool::class, ['limit' => 999])
        ->assertOk()
        ->assertSee('solo-project');
});

it('serves the llms.txt body as the site map resource', function () {
    $this->artisan('llms:generate')->assertSuccessful();

    PortfolioServer::resource(SiteMapResource::class)
        ->assertOk()
        ->assertSee('# Jefferson Gonçalves');
});

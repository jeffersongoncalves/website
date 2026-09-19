<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('emits Person JSON-LD and a skip link on the home page', function () {
    $response = $this->get('/pt_BR')->assertOk();

    $response->assertSee('application/ld+json', false);
    $response->assertSee('"@type":"Person"', false);
    $response->assertSee('href="#top"', false);
});

it('emits SoftwareSourceCode + BreadcrumbList JSON-LD and a per-repo OG image on a project page', function () {
    Storage::fake('github');
    Http::fake(['*' => Http::response('<h1>Readme</h1>', 200)]);

    $project = Project::query()->create([
        'name' => 'D3',
        'category' => ProjectCategory::JavascriptPackage,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/mbostock/d3',
        'published_at' => now(),
    ]);

    $response = $this->get('/pt_BR/projects/'.$project->slug)->assertOk();

    $response->assertSee('"@type":"SoftwareSourceCode"', false);
    $response->assertSee('"@type":"BreadcrumbList"', false);
    $response->assertSee('"codeRepository":"https://github.com/mbostock/d3"', false);
    // Per-project Open Graph image points at our cached proxy, not GitHub
    // directly (opengraph.githubassets.com rate-limits crawlers).
    $response->assertSee(route('og.show', ['slug' => $project->slug]), false);
    $response->assertDontSee('opengraph.githubassets.com', false);
    // README image CDNs are preconnected on pages that render a README.
    $response->assertSee('rel="preconnect" href="https://raw.githubusercontent.com"', false);

    // mbostock/d3 is third-party — the JSON-LD must NOT claim Jefferson authored it.
    $response->assertDontSee('"author":{', false);
});

it('claims authorship only for repos under the owner account', function () {
    Storage::fake('github');
    Http::fake(['*' => Http::response('<h1>Readme</h1>', 200)]);

    $owned = Project::query()->create([
        'name' => 'filament-gtag',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/filament-gtag',
        'published_at' => now(),
    ]);

    $this->get('/pt_BR/projects/'.$owned->slug)
        ->assertOk()
        ->assertSee('"author":{', false)
        ->assertSee('"name":"Jefferson Gonçalves"', false);
});

it('uses the project name (not its description) as the breadcrumb leaf', function () {
    Storage::fake('github');
    Http::fake(['*' => Http::response('<h1>Readme</h1>', 200)]);

    $project = Project::query()->create([
        'name' => 'D3',
        'title' => ['en' => 'A data-visualisation library'],
        'category' => ProjectCategory::JavascriptPackage,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/mbostock/d3',
        'published_at' => now(),
    ]);

    $this->get('/pt_BR/projects/'.$project->slug)
        ->assertOk()
        ->assertSee('"@type":"BreadcrumbList"', false)
        // leaf crumb is the name, never the long description
        ->assertSee('"name":"D3"', false)
        ->assertDontSee('"name":"A data-visualisation library"', false);
});

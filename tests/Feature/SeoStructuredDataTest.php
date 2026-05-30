<?php

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('emits Person JSON-LD and a skip link on the home page', function () {
    $response = $this->get('/')->assertOk();

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

    $response = $this->get('/projects/'.$project->slug)->assertOk();

    $response->assertSee('"@type":"SoftwareSourceCode"', false);
    $response->assertSee('"@type":"BreadcrumbList"', false);
    $response->assertSee('"codeRepository":"https://github.com/mbostock/d3"', false);
    // Per-project Open Graph image points at GitHub's repo social card.
    $response->assertSee('opengraph.githubassets.com/1/mbostock/d3', false);
    // README image CDNs are preconnected on pages that render a README.
    $response->assertSee('rel="preconnect" href="https://raw.githubusercontent.com"', false);
});

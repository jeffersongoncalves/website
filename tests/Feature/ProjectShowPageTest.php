<?php

declare(strict_types=1);

use App\Enums\PackageType;
use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Support\OutboundLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function pluginProject(array $attrs = []): Project
{
    return Project::query()->create(array_merge([
        'name' => 'Filament Thing',
        'slug' => 'filament-thing',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/acme/filament-thing',
        'published_at' => now(),
    ], $attrs));
}

function npmOnlyProject(array $attrs = []): Project
{
    return Project::query()->create(array_merge([
        'name' => 'tailwindcss-animate',
        'slug' => 'tailwindcss-animate',
        'category' => ProjectCategory::JavascriptPackage,
        'package_type' => PackageType::Npm,
        'status' => ProjectStatus::Published,
        'github_url' => null,
        'npm_url' => 'https://www.npmjs.com/package/tailwindcss-animate',
        'stars' => 0,
        'published_at' => now(),
    ], $attrs));
}

it('301s a project requested under the wrong section to its canonical section', function () {
    pluginProject(); // a Filament plugin is canonical under /projects/{slug}

    $this->get('/pt_BR/articles/filament-thing')
        ->assertStatus(301)
        ->assertRedirect(route('projects.show', ['slug' => 'filament-thing']));
});

it('merges consecutive versions that resolve to the same branch into one chip', function () {
    Storage::fake('github'); // README render writes to the github disk

    // v4 (2.x) and v5 (3.x) are both overridden onto `master`, so they collapse
    // into a single "v4/v5" chip; v3 (1.x) stays on its own.
    pluginProject([
        'versions' => ['v3', 'v4', 'v5'],
        'branch_overrides' => ['2.x' => 'master', '3.x' => 'master'],
    ]);

    $this->get('/pt_BR/projects/filament-thing')
        ->assertOk()
        ->assertSee('v4/v5')
        ->assertSee('v3');
});

it('renders a project page under its canonical section without redirecting', function () {
    Storage::fake('github');
    pluginProject();

    $this->get('/pt_BR/projects/filament-thing')
        ->assertOk()
        ->assertSee('Filament Thing');
});

it('sanitizes untrusted script/event-handler/Alpine markup out of a third-party README', function () {
    Storage::fake('github');
    Http::swap(new Factory);
    Http::fake([
        'api.github.com/repos/*/readme' => Http::response(
            "# Cool Tool\n\n".
            "<script>alert('xss')</script>\n\n".
            '<img src="x" onerror="alert(1)">'."\n\n".
            '<div x-data="{ open: true }" x-on:click="alert(2)">Alpine payload</div>'."\n\n".
            'Some **safe** text with a [link](https://example.test) and `inline code`.',
            200,
            ['Content-Type' => 'text/plain'],
        ),
        'api.github.com/repos/*' => Http::response(['default_branch' => 'main', 'stargazers_count' => 0]),
    ]);

    // Not a Filament plugin: no branch resolution needed, exercises the plain
    // github_url + null $ref path through the sanitizer.
    $project = Project::query()->create([
        'name' => 'Untrusted Readme Tool',
        'slug' => 'untrusted-readme-tool',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/acme/untrusted-readme-tool',
        'published_at' => now(),
    ]);

    $response = $this->get('/pt_BR/projects/'.$project->slug)->assertOk();

    // Check the exact injected payload strings, not generic tokens like
    // "<script" or "x-data" — the page's OWN trusted chrome legitimately has
    // <script> tags (Vite, GTM) and Alpine x-data elsewhere on this page.
    $response->assertDontSee("<script>alert('xss')</script>", false)
        ->assertDontSee('alert(1)', false)
        ->assertDontSee('alert(2)', false)
        ->assertDontSee('onerror="alert(1)"', false)
        ->assertDontSee('x-data="{ open: true }"', false)
        ->assertDontSee('x-on:click="alert(2)"', false)
        // Safe formatting survives sanitization (the link itself is rewritten
        // to an internal outbound short-url by GithubReadme::rewriteOutboundLinks,
        // so check the anchor text/tag rather than the literal external host).
        ->assertSee('safe', false)
        ->assertSee('inline code', false)
        ->assertSee('>link<', false)
        ->assertSee('Alpine payload', false);
});

it('assigns readme_branch as the ref for a non-Filament-plugin project', function () {
    Storage::fake('github');
    Http::swap(new Factory);
    Http::fake([
        // Trailing wildcard: a non-null $ref appends ?ref=<branch> to this
        // URL, and Http::fake's pattern match needs it or the broader
        // 'api.github.com/repos/*' stub below wins instead.
        'api.github.com/repos/*/readme*' => Http::response('# From develop', 200, ['Content-Type' => 'text/plain']),
        'api.github.com/repos/*' => Http::response(['default_branch' => 'main', 'stargazers_count' => 0]),
    ]);

    $project = Project::query()->create([
        'name' => 'Branch Pinned Tool',
        'slug' => 'branch-pinned-tool',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/acme/branch-pinned-tool',
        'readme_branch' => 'develop',
        'published_at' => now(),
    ]);

    $this->get('/pt_BR/projects/'.$project->slug)
        ->assertOk()
        ->assertSee('From develop');
});

it('renders the npm registry README for an npm-only package with no repo', function () {
    Http::fake([
        'registry.npmjs.org/*' => Http::response(['readme' => '# Animate utilities']),
    ]);

    npmOnlyProject();

    $this->get('/pt_BR/projects/tailwindcss-animate')
        ->assertOk()
        ->assertSee('Animate utilities');
});

it('shows the npm link and hides stars for an npm-only package with no repo', function () {
    Http::fake([
        'registry.npmjs.org/*' => Http::response(['readme' => '# Animate utilities']),
    ]);

    npmOnlyProject();

    // Off-site, so the button points at the tracked short URL, not npm itself.
    $this->get('/pt_BR/projects/tailwindcss-animate')
        ->assertOk()
        ->assertSee(OutboundLink::to('https://www.npmjs.com/package/tailwindcss-animate'))
        ->assertDontSee(__('site.projects.label_stars'));
});

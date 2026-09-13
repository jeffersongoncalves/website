<?php

declare(strict_types=1);

use App\Support\ProjectImporter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::flush();
});

function fakeGithubRepo(array $overrides = []): array
{
    return array_merge([
        'name' => 'filament-cep-field',
        'full_name' => 'jeffersongoncalves/filament-cep-field',
        'description' => 'Filament field for Brazilian CEP lookups.',
        'license' => ['spdx_id' => 'MIT'],
        'default_branch' => 'main',
        'homepage' => 'https://example.com',
        'topics' => ['filament-plugin', 'filament', 'cep', 'laravel'],
    ], $overrides);
}

function fakeComposer(array $overrides = []): array
{
    return array_merge([
        'name' => 'jeffersongoncalves/filament-cep-field',
        'description' => 'Filament field for Brazilian CEP lookups.',
        'type' => 'library',
        'require' => [
            'php' => '^8.2',
            'filament/filament' => '^3.0|^4.0|^5.0',
            'laravel/framework' => '^10.0|^11.0',
        ],
    ], $overrides);
}

function importerFakes(array $repo, ?array $composer, ?array $package, array $branches = [], bool $npmPublished = true, bool $hasDockerCompose = false, bool $packagistPublished = true): void
{
    // Packagist ownership check echoes the repo's own full_name as the
    // package `repository`, so a composer.json name that matches the imported
    // repo verifies as owned. Toggle the flag (or import a different repo) to
    // simulate a borrowed `name` (e.g. an app skeleton shipping laravel/laravel).
    $packagistRepository = 'https://github.com/'.($repo['full_name'] ?? 'foo/bar');

    Http::fake([
        'packagist.org/packages/*.json' => $packagistPublished
            ? Http::response(['package' => ['repository' => $packagistRepository]], 200)
            : Http::response('', 404),
        'api.github.com/repos/*/branches*' => Http::response(array_map(fn ($b) => ['name' => $b], $branches)),
        'api.github.com/repos/*' => Http::response($repo),
        'raw.githubusercontent.com/*/composer.json' => $composer !== null
            ? Http::response($composer)
            : Http::response('', 404),
        'raw.githubusercontent.com/*/package.json' => $package !== null
            ? Http::response($package)
            : Http::response('', 404),
        // Docker detector checks ten candidate paths via HEAD — stub a 200
        // for the canonical `docker-compose.yml` when the flag is on, and
        // a 404 for every other docker-* / Dockerfile path so tests don't
        // hit real network through the wildcard fall-through.
        'raw.githubusercontent.com/*/docker-compose.yml' => $hasDockerCompose
            ? Http::response('', 200)
            : Http::response('', 404),
        'raw.githubusercontent.com/*docker-compose.yaml' => Http::response('', 404),
        'raw.githubusercontent.com/*compose.yml' => Http::response('', 404),
        'raw.githubusercontent.com/*compose.yaml' => Http::response('', 404),
        'raw.githubusercontent.com/*/Dockerfile' => Http::response('', 404),
        // Stub the npm registry lookup so tests don't hit real network. The
        // HEAD (npmPackageExists) ignores the body; the GET
        // (npmPackageBelongsToRepo) needs a `repository` pointing back at the
        // imported repo, so echo the canonical `foo/bar` slug the github fakes
        // use. Toggle the flag to simulate `package.json` present but no
        // published package.
        'registry.npmjs.org/*' => $npmPublished
            ? Http::response(['repository' => ['type' => 'git', 'url' => 'https://github.com/foo/bar']], 200)
            : Http::response('', 404),
    ]);
}

it('returns an error for an invalid github URL', function (): void {
    $result = ProjectImporter::fromGithub('not-a-url');

    expect($result)->toBe(['error' => 'invalid_url']);
});

it('imports a Filament plugin with composer.json', function (): void {
    importerFakes(fakeGithubRepo(), fakeComposer(), null, ['main', '3.x', '4.x', '5.x']);

    $result = ProjectImporter::fromGithub('https://github.com/jeffersongoncalves/filament-cep-field');

    expect($result['error'] ?? null)->toBeNull();
    $fields = $result['fields'];
    expect($fields['name'])->toBe('Filament Cep Field');
    expect($fields['slug'])->toBe('jeffersongoncalves-filament-cep-field');
    expect($fields['repo'])->toBe('filament-cep-field');
    expect($fields['license'])->toBe('MIT');
    expect($fields['readme_branch'])->toBe('main');
    expect($fields['docs_url'])->toBe('https://example.com');
    expect($fields['title.en'])->toBe('Filament field for Brazilian CEP lookups.');
    expect($fields['category'])->toBe('filament_plugin');
    expect($fields['package_type'])->toBe('composer');
    expect($fields['packagist_url'])->toBe('https://packagist.org/packages/jeffersongoncalves/filament-cep-field');
    expect($fields['npm_url'])->toBeNull();
    expect($fields['stack'])->toBe(['Laravel', 'Filament']);
    expect($fields['versions'])->toBe(['v3', 'v4', 'v5']);
    // The repo ships 3.x/4.x/5.x branches, so the admin form must open with the
    // versions + branch_overrides fields already unlocked.
    expect($fields['has_branches'])->toBeTrue();
});

it('leaves has_branches off for a plugin that ships every version from one branch', function (): void {
    importerFakes(fakeGithubRepo(), fakeComposer(), null, ['main']);

    $result = ProjectImporter::fromGithub('https://github.com/jeffersongoncalves/filament-cep-field');

    expect($result['fields']['versions'])->toBe(['v3', 'v4', 'v5'])
        ->and($result['fields']['has_branches'])->toBeFalse();
});

it('unions versions across every N.x branch instead of only the default branch (real bug: jeffersongoncalves-filament-page-visits reported only v5)', function (): void {
    // Real-world shape: unlike the combined-constraint fixture above, each
    // branch here declares only ITS OWN Filament major — the default branch
    // (3.x, current) only knows about v5, so reading just that composer.json
    // (the pre-fix behavior) silently drops v3/v4 entirely.
    $repo = fakeGithubRepo([
        'full_name' => 'jeffersongoncalves/filament-page-visits',
        'default_branch' => '3.x',
    ]);

    Http::fake([
        'packagist.org/packages/*.json' => Http::response(['package' => ['repository' => 'https://github.com/jeffersongoncalves/filament-page-visits']], 200),
        'api.github.com/repos/*/branches*' => Http::response([['name' => '1.x'], ['name' => '2.x'], ['name' => '3.x']]),
        'api.github.com/repos/*' => Http::response($repo),
        'raw.githubusercontent.com/jeffersongoncalves/filament-page-visits/1.x/composer.json' => Http::response(fakeComposer(['require' => ['filament/filament' => '^3.0']])),
        'raw.githubusercontent.com/jeffersongoncalves/filament-page-visits/2.x/composer.json' => Http::response(fakeComposer(['require' => ['filament/filament' => '^4.0']])),
        'raw.githubusercontent.com/jeffersongoncalves/filament-page-visits/3.x/composer.json' => Http::response(fakeComposer(['require' => ['filament/filament' => '^5.0']])),
        'raw.githubusercontent.com/*/package.json' => Http::response('', 404),
        'raw.githubusercontent.com/*docker*' => Http::response('', 404),
        'raw.githubusercontent.com/*Dockerfile' => Http::response('', 404),
        'registry.npmjs.org/*' => Http::response('', 404),
    ]);

    $result = ProjectImporter::fromGithub('https://github.com/jeffersongoncalves/filament-page-visits');

    expect($result['fields']['versions'])->toBe(['v3', 'v4', 'v5'])
        ->and($result['fields']['has_branches'])->toBeTrue();
});

it('does not attach packagist_url when composer.json ships a borrowed name (app skeleton)', function (): void {
    // Real-world bug: savanihd/Laravel-11-Livewire-CRUD ships composer.json with
    // "name": "laravel/laravel". laravel/laravel IS a real package, but its
    // Packagist repository is laravel/laravel — not the imported fork — so the
    // link must be dropped, not advertised.
    Http::fake([
        'api.github.com/repos/*/branches*' => Http::response([['name' => 'main']]),
        'api.github.com/repos/*' => Http::response(fakeGithubRepo([
            'name' => 'Laravel-11-Livewire-CRUD',
            'full_name' => 'savanihd/Laravel-11-Livewire-CRUD',
            'topics' => [],
        ])),
        'raw.githubusercontent.com/*/composer.json' => Http::response(fakeComposer(['name' => 'laravel/laravel', 'type' => 'project'])),
        'raw.githubusercontent.com/*/package.json' => Http::response('', 404),
        // Packagist returns the real laravel/laravel, whose repository points at
        // laravel/laravel — NOT the imported repo.
        'packagist.org/packages/*.json' => Http::response(['package' => ['repository' => 'https://github.com/laravel/laravel']], 200),
        // Docker probes / any other raw path.
        'raw.githubusercontent.com/*' => Http::response('', 404),
    ]);

    $result = ProjectImporter::fromGithub('https://github.com/savanihd/Laravel-11-Livewire-CRUD');
    $fields = $result['fields'];

    // A borrowed name is definitively FOREIGN → the repo is an application, not
    // a Composer package: no packagist_url AND package_type downgraded to none so
    // no future metrics sync re-derives a link or counts foreign downloads.
    expect($fields['packagist_url'])->toBeNull()
        ->and($fields['package_type'])->toBe('none')
        ->and($result['warnings'])->toContain('packagist_not_owned');
});

it('resolves npm package_type when composer.json is missing but package.json exists', function (): void {
    importerFakes(
        fakeGithubRepo(['topics' => ['javascript']]),
        null,
        ['name' => 'my-pkg', 'description' => 'A JS lib', 'dependencies' => ['alpinejs' => '^3', 'tailwindcss' => '^4']],
    );

    $result = ProjectImporter::fromGithub('https://github.com/foo/bar');

    $fields = $result['fields'];
    expect($fields['package_type'])->toBe('npm');
    expect($fields['npm_url'])->toBe('https://www.npmjs.com/package/my-pkg');
    expect($fields['packagist_url'])->toBeNull();
    expect($fields['stack'])->toBe(['Tailwind', 'Alpine.js']);
    expect($fields['category'])->toBe('javascript_package');
});

it('falls back to the repo name for the title when there is no description', function (): void {
    importerFakes(
        fakeGithubRepo(['name' => 'cool-tool', 'description' => null, 'topics' => []]),
        null,
        null,
    );

    $fields = ProjectImporter::fromGithub('https://github.com/foo/cool-tool')['fields'];

    expect($fields['title.en'])->toBe('Cool Tool');
    expect($fields['title.pt'])->toBe('Cool Tool');
    expect($fields['title.es'])->toBe('Cool Tool');
});

it('stores Other for a NOASSERTION license instead of the opaque token', function (): void {
    importerFakes(
        fakeGithubRepo(['license' => ['spdx_id' => 'NOASSERTION'], 'topics' => []]),
        null,
        null,
    );

    $fields = ProjectImporter::fromGithub('https://github.com/foo/custom-licensed')['fields'];

    expect($fields['license'])->toBe('Other');
});

it('keeps the MIT default when github reports no license', function (): void {
    importerFakes(
        fakeGithubRepo(['license' => null, 'topics' => []]),
        null,
        null,
    );

    $fields = ProjectImporter::fromGithub('https://github.com/foo/unlicensed')['fields'];

    expect($fields['license'])->toBe('MIT');
});

it('captures and normalizes topics from github + composer keywords', function (): void {
    importerFakes(
        fakeGithubRepo(['topics' => ['Filament', 'laravel']]),
        fakeComposer(['keywords' => ['PHP', 'filament']]),
        null,
        ['main'],
    );

    $fields = ProjectImporter::fromGithub('https://github.com/foo/bar')['fields'];

    expect($fields['topics'])->toBe(['filament', 'laravel', 'php']);
});

it('captures the repo primary language from the github metadata', function (): void {
    importerFakes(fakeGithubRepo(['language' => 'PHP']), fakeComposer(), null, ['main']);

    $fields = ProjectImporter::fromGithub('https://github.com/foo/bar')['fields'];

    expect($fields['language'])->toBe('PHP');
});

it('leaves language null when github reports none', function (): void {
    importerFakes(fakeGithubRepo(['language' => null]), fakeComposer(), null, ['main']);

    $fields = ProjectImporter::fromGithub('https://github.com/foo/baz')['fields'];

    expect($fields['language'])->toBeNull();
});

it('drops npm_url and downgrades package_type when package.json exists but the registry has no published package', function (): void {
    importerFakes(
        fakeGithubRepo(['topics' => ['javascript']]),
        null,
        ['name' => 'caveman-installer', 'description' => 'A CLI installer'],
        npmPublished: false,
    );

    $result = ProjectImporter::fromGithub('https://github.com/foo/caveman');

    $fields = $result['fields'];
    expect($fields['npm_url'])->toBeNull();
    expect($fields['package_type'])->toBe('none');
    expect($result['warnings'] ?? [])->toContain('npm_not_published');
});

it('mirrors the same description across every translatable locale on a github import', function (): void {
    importerFakes(fakeGithubRepo(), fakeComposer(), null, ['main', '3.x', '4.x', '5.x']);

    $fields = ProjectImporter::fromGithub('https://github.com/foo/bar')['fields'];

    expect($fields['title.en'])->toBe('Filament field for Brazilian CEP lookups.');
    expect($fields['title.pt'])->toBe('Filament field for Brazilian CEP lookups.');
    expect($fields['title.es'])->toBe('Filament field for Brazilian CEP lookups.');
});

it('returns repo_not_found when GitHub API 404s', function (): void {
    Http::fake([
        'api.github.com/repos/*' => Http::response('', 404),
    ]);

    $result = ProjectImporter::fromGithub('https://github.com/foo/missing');

    expect($result)->toBe(['error' => 'repo_not_found']);
});

it('falls back to application category and emits a warning when nothing matches', function (): void {
    importerFakes(
        fakeGithubRepo(['topics' => [], 'description' => null]),
        null,
        null,
    );

    $result = ProjectImporter::fromGithub('https://github.com/foo/bar');

    expect($result['fields']['category'])->toBe('application');
    expect($result['warnings'])->toContain('category_fallback');
});

it('imports from a generic URL using <head> meta tags', function (): void {
    $html = <<<'HTML'
    <!doctype html>
    <html>
      <head>
        <title>Linear — The new standard for software teams</title>
        <meta name="description" content="Linear streamlines issues, projects, and product roadmaps.">
        <meta property="og:title" content="Linear">
        <meta property="og:description" content="The issue tracker built for high-performance teams.">
      </head>
    </html>
    HTML;

    Http::fake([
        'linear.app' => Http::response($html, 200, ['Content-Type' => 'text/html']),
    ]);

    $result = ProjectImporter::fromUrl('https://linear.app');

    expect($result['error'] ?? null)->toBeNull();
    $fields = $result['fields'];
    expect($fields['name'])->toBe('Linear');
    expect($fields['slug'])->toBe('site-linear-app');
    expect($fields['docs_url'])->toBe('https://linear.app');
    expect($fields['github_url'])->toBeNull();
    expect($fields['category'])->toBe('website');
    expect($fields['package_type'])->toBe('none');
    expect($fields['title.en'])->toBe('The issue tracker built for high-performance teams.');
    expect($fields['title.pt'])->toBe('The issue tracker built for high-performance teams.');
});

it('keeps the scope in the display name for a scoped npm package', function (): void {
    Http::fake([
        'registry.npmjs.org/@tailwindcss/vite' => Http::response([
            'name' => '@tailwindcss/vite',
            'description' => 'A Vite plugin for Tailwind CSS.',
        ], 200),
    ]);

    $result = ProjectImporter::fromNpm('https://www.npmjs.com/package/@tailwindcss/vite');

    $fields = $result['fields'];
    expect($fields['name'])->toBe('Tailwindcss Vite');
    expect($fields['slug'])->toBe('tailwindcss-vite');
    expect($fields['npm_url'])->toBe('https://www.npmjs.com/package/@tailwindcss/vite');
});

it('points github_url at the monorepo subdirectory and warns when the subdirectory has no README', function (): void {
    Http::fake([
        'registry.npmjs.org/@alpinejs/anchor' => Http::response([
            'name' => '@alpinejs/anchor',
            'description' => 'Alpine anchor plugin.',
            'repository' => [
                'type' => 'git',
                'url' => 'git+https://github.com/alpinejs/alpine.git',
                'directory' => 'packages/anchor',
            ],
        ], 200),
        'api.github.com/repos/alpinejs/alpine/readme/packages/anchor' => Http::response('', 404),
        'api.github.com/repos/alpinejs/alpine' => Http::response(['default_branch' => 'main'], 200),
    ]);

    $result = ProjectImporter::fromNpm('https://www.npmjs.com/package/@alpinejs/anchor');

    expect($result['fields']['github_url'])
        ->toBe('https://github.com/alpinejs/alpine/tree/main/packages/anchor');
    expect($result['warnings'])->toContain('no_directory_readme');
});

it('omits the directory-readme warning when the subdirectory ships a README', function (): void {
    Http::fake([
        'registry.npmjs.org/@tailwindcss/vite' => Http::response([
            'name' => '@tailwindcss/vite',
            'description' => 'Vite plugin for Tailwind.',
            'repository' => [
                'type' => 'git',
                'url' => 'https://github.com/tailwindlabs/tailwindcss.git',
                'directory' => 'packages/@tailwindcss-vite',
            ],
        ], 200),
        // %40, not @ — GitHubClient rawurlencodes each path segment, so the
        // request this stub has to match carries the encoded scope.
        'api.github.com/repos/tailwindlabs/tailwindcss/readme/packages/%40tailwindcss-vite' => Http::response([
            'name' => 'README.md',
            'path' => 'packages/@tailwindcss-vite/README.md',
        ], 200),
        'api.github.com/repos/tailwindlabs/tailwindcss' => Http::response(['default_branch' => 'main'], 200),
    ]);

    $result = ProjectImporter::fromNpm('https://www.npmjs.com/package/@tailwindcss/vite');

    expect($result['fields']['github_url'])
        ->toBe('https://github.com/tailwindlabs/tailwindcss/tree/main/packages/@tailwindcss-vite');
    expect($result['warnings'] ?? [])->not->toContain('no_directory_readme');
});

it('returns invalid_url for non-http schemes', function (): void {
    $result = ProjectImporter::fromUrl('ftp://example.com');

    expect($result)->toBe(['error' => 'invalid_url']);
});

it('imports a YouTube channel via Open Graph and forces the youtube_channel category', function (): void {
    $html = <<<'HTML'
    <!doctype html>
    <html>
      <head>
        <meta property="og:title" content="Akitando - YouTube">
        <meta property="og:description" content="Blog do Fabio Akita falando sobre tecnologia.">
      </head>
    </html>
    HTML;

    Http::fake([
        'www.youtube.com/@Akitando' => Http::response($html, 200, ['Content-Type' => 'text/html']),
    ]);

    $result = ProjectImporter::fromYoutube('https://www.youtube.com/@Akitando');

    expect($result['error'] ?? null)->toBeNull();
    $fields = $result['fields'];
    expect($fields['name'])->toBe('Akitando');
    expect($fields['slug'])->toBe('youtube-akitando');
    expect($fields['category'])->toBe('youtube_channel');
    expect($fields['package_type'])->toBe('none');
    expect($fields['docs_url'])->toBe('https://www.youtube.com/@Akitando');
    expect($fields['title.pt'])->toBe('Blog do Fabio Akita falando sobre tecnologia.');
});

it('rejects URLs that do not look like a YouTube channel', function (): void {
    $result = ProjectImporter::fromYoutube('https://www.youtube.com/watch?v=abc');

    expect($result)->toBe(['error' => 'invalid_url']);
});

it('returns fetch_failed when the URL responds with non-2xx', function (): void {
    Http::fake([
        '*' => Http::response('', 500),
    ]);

    $result = ProjectImporter::fromUrl('https://example.com');

    expect($result)->toBe(['error' => 'fetch_failed']);
});

it('caches results so the second call does not hit the network', function (): void {
    importerFakes(fakeGithubRepo(), fakeComposer(), null, ['main', '3.x', '4.x', '5.x']);

    ProjectImporter::fromGithub('https://github.com/jeffersongoncalves/filament-cep-field');

    Http::fake(fn () => throw new RuntimeException('cached call should not hit network'));

    $second = ProjectImporter::fromGithub('https://github.com/jeffersongoncalves/filament-cep-field');

    expect($second['fields']['name'])->toBe('Filament Cep Field');
});

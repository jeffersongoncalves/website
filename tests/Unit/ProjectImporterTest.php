<?php

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

function importerFakes(array $repo, ?array $composer, ?array $package, array $branches = [], bool $npmPublished = true, bool $hasDockerCompose = false): void
{
    Http::fake([
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
        // Stub the npm registry HEAD lookup so tests don't hit real network.
        // Default OK = pretend the package is published; toggle with the flag
        // to simulate `package.json` present but no published package.
        'registry.npmjs.org/*' => $npmPublished
            ? Http::response('', 200)
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
    expect($fields['category'])->toBe('tool');
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

it('falls back to tool category and emits a warning when nothing matches', function (): void {
    importerFakes(
        fakeGithubRepo(['topics' => [], 'description' => null]),
        null,
        null,
    );

    $result = ProjectImporter::fromGithub('https://github.com/foo/bar');

    expect($result['fields']['category'])->toBe('tool');
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
    expect($fields['slug'])->toBe('linear-app');
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
        'api.github.com/repos/tailwindlabs/tailwindcss/readme/packages/@tailwindcss-vite' => Http::response([
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

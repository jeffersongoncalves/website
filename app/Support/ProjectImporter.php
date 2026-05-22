<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ProjectImporter
{
    /**
     * Fetch a GitHub repo + composer.json + package.json + branches and
     * return a flat array of form fields plus warnings the caller should
     * surface to the editor. Result is cached for an hour per repo so
     * re-opening the modal during a single edit session doesn't hammer the
     * API. Returns `['error' => '<key>']` on any unrecoverable failure.
     *
     * @return array{fields?: array<string, mixed>, warnings?: list<string>, error?: string}
     */
    public static function fromGithub(string $url): array
    {
        $repoSlug = GithubReadme::repoFromUrl($url);

        if (! $repoSlug) {
            return ['error' => 'invalid_url'];
        }

        return Cache::remember(
            "project_importer:github:{$repoSlug}",
            now()->addHour(),
            fn () => self::buildResult($repoSlug, $url)
        );
    }

    /**
     * Fetch a generic website and extract metadata from its <head> tags.
     * Use for projects with no public GitHub repo (SaaS tools, hosted
     * services, etc). Only fills name/title/docs_url + sane defaults
     * for the form's required selects.
     *
     * @return array{fields?: array<string, mixed>, warnings?: list<string>, error?: string}
     */
    public static function fromUrl(string $url): array
    {
        $url = trim($url);

        if (! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $url)) {
            return ['error' => 'invalid_url'];
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return ['error' => 'invalid_url'];
        }

        return Cache::remember(
            'project_importer:url:'.sha1($url),
            now()->addHour(),
            fn () => self::buildUrlResult($url, $host)
        );
    }

    /**
     * Fetch an npm package from the public registry and map its manifest to
     * form fields. Mirrors fromGithub's shape: name/description/license come
     * from the registry document, github_url is recovered from the
     * `repository` field and docs_url from `homepage`. Cached for an hour per
     * package. Returns `['error' => '<key>']` on failure.
     *
     * @return array{fields?: array<string, mixed>, warnings?: list<string>, error?: string}
     */
    public static function fromNpm(string $url): array
    {
        $package = self::npmNameFromUrl($url);

        if ($package === null) {
            return ['error' => 'invalid_url'];
        }

        return Cache::remember(
            "project_importer:npm:{$package}",
            now()->addHour(),
            fn () => self::buildNpmResult($package)
        );
    }

    /**
     * @return array{fields?: array<string, mixed>, warnings?: list<string>, error?: string}
     */
    private static function buildNpmResult(string $package): array
    {
        $data = self::fetchNpmRegistry($package);

        if ($data === null) {
            return ['error' => 'repo_not_found'];
        }

        $name = is_string($data['name'] ?? null) ? $data['name'] : $package;
        $description = self::nullableString($data['description'] ?? null);
        $homepage = self::nullableString($data['homepage'] ?? null);
        $githubUrl = self::githubUrlFromRepository($data['repository'] ?? null);
        $license = self::npmLicense($data['license'] ?? null);

        // Drop docs_url when homepage just points back at the GitHub repo —
        // github_url already covers that, no need to duplicate the link.
        $docsUrl = $homepage;
        if ($docsUrl !== null && $githubUrl !== null && rtrim($docsUrl, '/') === rtrim($githubUrl, '/')) {
            $docsUrl = null;
        }

        $fields = [
            'github_url' => $githubUrl,
            // Strip the `@scope/` punctuation before slugging — Str::slug would
            // otherwise transliterate `@` to "at" (@scope/name → at-scopename).
            'slug' => Str::slug(str_replace(['@', '/'], ['', '-'], $name)),
            'name' => self::prettifyNpmName($name),
            'repo' => null,
            'license' => $license,
            'readme_branch' => null,
            'docs_url' => $docsUrl,
            'title.en' => $description,
            'title.pt' => $description,
            'title.es' => $description,
            'category' => 'tool',
            'package_type' => 'npm',
            'packagist_url' => null,
            'npm_url' => 'https://www.npmjs.com/package/'.$name,
            'stack' => [],
            'versions' => [],
        ];

        $warnings = [];

        if ($description === null) {
            $warnings[] = 'no_description';
        }

        return ['fields' => $fields, 'warnings' => $warnings];
    }

    /**
     * Extract the package identifier (`name` or `@scope/name`) from an
     * npmjs.com/package URL. Returns null for anything else.
     */
    private static function npmNameFromUrl(string $url): ?string
    {
        if (! preg_match('~npmjs\.com/package/(@[^/?#]+/[^/?#]+|[^/?#]+)~i', trim($url), $m)) {
            return null;
        }

        return rtrim($m[1], '/');
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function fetchNpmRegistry(string $package): ?array
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'jeffersongoncalves-site',
                    'Accept' => 'application/json',
                ])
                ->get('https://registry.npmjs.org/'.$package);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Recover a clean https GitHub URL from an npm `repository` field, which
     * can be a string or `{type, url}` and is usually a git remote like
     * `git+https://github.com/owner/repo.git`. Returns null when the remote
     * isn't a GitHub one.
     */
    private static function githubUrlFromRepository(mixed $repository): ?string
    {
        if (is_string($repository)) {
            $raw = $repository;
        } elseif (is_array($repository) && is_string($repository['url'] ?? null)) {
            $raw = $repository['url'];
        } else {
            return null;
        }

        if (! preg_match('~github\.com[/:]([^/]+/[^/?#]+?)(?:\.git)?(?:[/?#]|$)~i', $raw, $m)) {
            return null;
        }

        return 'https://github.com/'.$m[1];
    }

    /**
     * Normalise the npm `license` field, which is a SPDX string on modern
     * packages but a legacy `{type, url}` object on older ones.
     */
    private static function npmLicense(mixed $license): ?string
    {
        if (is_string($license)) {
            return self::nullableString($license);
        }

        if (is_array($license) && is_string($license['type'] ?? null)) {
            return self::nullableString($license['type']);
        }

        return null;
    }

    /**
     * Prettify an npm package name for display. Scoped packages keep their
     * scope so a generic unscoped name stays meaningful: `@tailwindcss/vite`
     * → "Tailwindcss Vite" rather than a bare "Vite".
     */
    private static function prettifyNpmName(string $name): string
    {
        if (str_starts_with($name, '@') && str_contains($name, '/')) {
            return self::prettifyName(str_replace('/', '-', ltrim($name, '@')));
        }

        return self::prettifyName($name);
    }

    /**
     * @return array{fields?: array<string, mixed>, warnings?: list<string>, error?: string}
     */
    private static function buildUrlResult(string $url, string $host): array
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'jeffersongoncalves-site',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->get($url);
        } catch (Throwable) {
            return ['error' => 'fetch_failed'];
        }

        if (! $response->successful()) {
            return ['error' => 'fetch_failed'];
        }

        $meta = self::parseMeta($response->body());

        $name = self::nameFromHost($host);
        $description = $meta['og:description'] ?? $meta['description'] ?? null;
        $title = $meta['og:title'] ?? $meta['title'] ?? $name;

        $fields = [
            'github_url' => null,
            'slug' => self::slugFromHost($host),
            'name' => $name,
            'repo' => null,
            'license' => null,
            'readme_branch' => null,
            'docs_url' => $url,
            'title.en' => $description ?? $title,
            'title.pt' => $description ?? $title,
            'title.es' => $description ?? $title,
            'category' => 'tool',
            'package_type' => 'none',
            'packagist_url' => null,
            'npm_url' => null,
            'stack' => [],
            'versions' => [],
        ];

        $warnings = [];

        if ($description === null) {
            $warnings[] = 'no_description';
        }

        return ['fields' => $fields, 'warnings' => $warnings];
    }

    /**
     * Best-effort meta extraction from an HTML <head>. Reads <title>, the
     * standard <meta name="description">, and the Open Graph counterparts.
     * Silently ignores libxml warnings on malformed markup.
     *
     * @return array<string, string>
     */
    private static function parseMeta(string $html): array
    {
        $meta = [];

        $previous = libxml_use_internal_errors(true);

        $doc = new \DOMDocument;
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html);

        $titleNodes = $doc->getElementsByTagName('title');
        if ($titleNodes->length > 0) {
            $value = trim((string) $titleNodes->item(0)?->textContent);
            if ($value !== '') {
                $meta['title'] = $value;
            }
        }

        foreach ($doc->getElementsByTagName('meta') as $node) {
            $property = $node->getAttribute('property') ?: $node->getAttribute('name');
            $content = trim($node->getAttribute('content'));
            if ($property === '' || $content === '') {
                continue;
            }
            $key = strtolower($property);
            if (in_array($key, ['title', 'description', 'og:title', 'og:description'], true)) {
                $meta[$key] = $content;
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $meta;
    }

    private static function nameFromHost(string $host): string
    {
        $host = preg_replace('/^www\./i', '', $host) ?? $host;
        $first = explode('.', $host)[0];

        return ucfirst($first);
    }

    private static function slugFromHost(string $host): string
    {
        $host = preg_replace('/^www\./i', '', $host) ?? $host;

        return strtolower(str_replace('.', '-', $host));
    }

    /**
     * @return array{fields?: array<string, mixed>, warnings?: list<string>, error?: string}
     */
    private static function buildResult(string $repoSlug, string $url): array
    {
        $repo = self::fetchRepo($repoSlug);

        if ($repo === null) {
            return ['error' => 'repo_not_found'];
        }

        $branch = is_string($repo['default_branch'] ?? null) ? $repo['default_branch'] : 'main';
        $composer = self::fetchManifest($repoSlug, $branch, 'composer.json');
        $package = self::fetchManifest($repoSlug, $branch, 'package.json');
        // Docker classification signals — checked on root + the two common
        // self-hosted layouts (plausible/analytics ships `hosting/`, several
        // others ship `installer/`). HEAD against raw.githubusercontent.com
        // so we don't pull the file contents.
        $composePaths = [
            'docker-compose.yml',
            'docker-compose.yaml',
            'compose.yml',
            'compose.yaml',
            'hosting/docker-compose.yml',
            'hosting/docker-compose.yaml',
            'installer/docker-compose.yml',
            'installer/docker-compose.yaml',
            'docker/docker-compose.yml',
            'docker/docker-compose.yaml',
        ];
        $hasDockerCompose = false;
        foreach ($composePaths as $path) {
            if (self::fileExists($repoSlug, $branch, $path)) {
                $hasDockerCompose = true;
                break;
            }
        }
        // Standalone Dockerfile counts as a Docker signal only when the
        // repo doesn't ship composer.json / package.json — otherwise a
        // Laravel app with a dev Dockerfile would be misclassified.
        $hasStandaloneDockerfile = $composer === null
            && $package === null
            && self::fileExists($repoSlug, $branch, 'Dockerfile');
        $branches = self::fetchBranches($repoSlug);

        [$owner, $repoName] = explode('/', $repoSlug, 2);

        $category = self::resolveCategory($composer, $repo, $hasDockerCompose || $hasStandaloneDockerfile);

        // Only treat the project as an npm package if it's actually published.
        // A `package.json` checked into the repo is not enough — many repos
        // ship one for tooling (eslint, vite, prettier) without ever pushing
        // to the registry. Hit `registry.npmjs.org` HEAD before claiming npm.
        $npmName = self::extractNpmName($package);
        $npmPublished = $npmName !== null && self::npmPackageExists($npmName);

        $packageType = self::resolvePackageType($composer, $package, $repo, $npmPublished);
        // Docker-distributed projects don't fit any of the package-manager
        // types resolvePackageType knows about, but they still ship as an
        // image — flip the type so the badge + downloads tracker uses the
        // Docker rail instead of falling back to `none`.
        if ($category === 'docker' && $packageType === 'none') {
            $packageType = 'docker';
        }
        $description = self::pickDescription($composer, $package, $repo);

        $fields = [
            'github_url' => $url,
            'slug' => Str::slug($owner.'-'.$repoName),
            'name' => self::prettifyName($repoName),
            'repo' => $repoName,
            'license' => is_string($repo['license']['spdx_id'] ?? null) ? $repo['license']['spdx_id'] : 'MIT',
            'readme_branch' => $branch,
            'docs_url' => self::nullableString($repo['homepage'] ?? null),
            // Mirror the same description across all locales — the importer can't
            // translate, the editor manually edits per-locale later. Same value
            // beats null fields the editor has to clear.
            'title.en' => $description,
            'title.pt' => $description,
            'title.es' => $description,
            'category' => $category,
            'package_type' => $packageType,
            'packagist_url' => self::buildPackagistUrl($composer),
            'npm_url' => $npmPublished ? 'https://www.npmjs.com/package/'.$npmName : null,
            'stack' => self::resolveStack($composer, $package),
            'versions' => self::resolveVersions($composer, $branches, $category),
        ];

        $warnings = [];

        if ($category === 'tool' && ! isset($composer['type'])) {
            $warnings[] = 'category_fallback';
        }

        if ($description === null) {
            $warnings[] = 'no_description';
        }

        if ($npmName !== null && ! $npmPublished) {
            $warnings[] = 'npm_not_published';
        }

        return ['fields' => $fields, 'warnings' => $warnings];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function fetchRepo(string $repoSlug): ?array
    {
        $response = self::githubGet("https://api.github.com/repos/{$repoSlug}");

        if ($response === null || ! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function fetchManifest(string $repoSlug, string $branch, string $file): ?array
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'jeffersongoncalves-site'])
                ->get("https://raw.githubusercontent.com/{$repoSlug}/{$branch}/{$file}");
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * @return list<string>
     */
    private static function fetchBranches(string $repoSlug): array
    {
        $response = self::githubGet("https://api.github.com/repos/{$repoSlug}/branches", ['per_page' => 100]);

        if ($response === null || ! $response->successful()) {
            return [];
        }

        $names = [];

        foreach ((array) $response->json() as $branch) {
            if (is_array($branch) && isset($branch['name']) && is_string($branch['name'])) {
                $names[] = $branch['name'];
            }
        }

        return $names;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private static function githubGet(string $url, array $params = []): ?Response
    {
        $headers = ['User-Agent' => 'jeffersongoncalves-site', 'Accept' => 'application/vnd.github+json'];

        if ($token = config('services.github.token')) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        try {
            return Http::timeout(8)->withHeaders($headers)->get($url, $params);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>|null  $composer
     * @param  array<string, mixed>|null  $package
     * @param  array<string, mixed>  $repo
     */
    private static function resolvePackageType(?array $composer, ?array $package, array $repo, bool $npmPublished): string
    {
        if ($composer !== null) {
            return 'composer';
        }

        if ($package !== null && $npmPublished) {
            return 'npm';
        }

        $homepage = is_string($repo['homepage'] ?? null) ? (string) $repo['homepage'] : '';
        if ($homepage !== '' && str_contains($homepage, 'plugins.jetbrains.com')) {
            return 'jetbrains';
        }

        return 'none';
    }

    /**
     * Pull the package name out of a parsed `package.json`, if it's a valid
     * npm identifier. Used to gate the registry lookup so we don't ping
     * npmjs.org for repos that don't ship a package.json at all.
     *
     * @param  array<string, mixed>|null  $package
     */
    private static function extractNpmName(?array $package): ?string
    {
        $name = $package['name'] ?? null;

        if (! is_string($name) || ! preg_match('#^(@[a-z0-9_.~-]+/)?[a-z0-9_.~-]+$#i', $name)) {
            return null;
        }

        return $name;
    }

    /**
     * HEAD the public npm registry to verify a package was actually
     * published under this name. Hitting the JSON GET would also work but
     * HEAD is cheaper and the body isn't needed — we only care about the
     * 200 vs 404 distinction. Network failures degrade to "not published"
     * so a flaky registry doesn't fake a package_type=npm.
     */
    private static function npmPackageExists(string $name): bool
    {
        // Scoped packages keep the slash unencoded in the registry URL.
        $url = 'https://registry.npmjs.org/'.$name;

        try {
            $response = Http::timeout(6)
                ->withHeaders([
                    'User-Agent' => 'jeffersongoncalves-site',
                    'Accept' => 'application/json',
                ])
                ->head($url);
        } catch (Throwable) {
            return false;
        }

        return $response->successful();
    }

    /**
     * Priority order matters — a Filament plugin that also requires Laravel
     * should still resolve as a filament_plugin, not laravel_package. Docker
     * sits below all PHP-stack classifications because a repo with both a
     * composer.json AND a docker-compose.yml is still primarily the PHP
     * package; the Docker fallback is for self-hosted apps that ship as
     * compose stacks (e.g. plausible/analytics).
     *
     * @param  array<string, mixed>|null  $composer
     * @param  array<string, mixed>  $repo
     */
    private static function resolveCategory(?array $composer, array $repo, bool $hasDockerCompose = false): string
    {
        $topics = is_array($repo['topics'] ?? null) ? $repo['topics'] : [];
        $require = is_array($composer['require'] ?? null) ? $composer['require'] : [];
        $type = $composer['type'] ?? null;
        $vendor = explode('/', is_string($composer['name'] ?? null) ? (string) $composer['name'] : '/')[0];

        if ($type === 'filament-plugin') {
            return 'filament_plugin';
        }

        if (in_array('filament-plugin', $topics, true)) {
            return 'filament_plugin';
        }

        if (in_array('filament', $topics, true) && self::hasAnyKey($require, 'filament/')) {
            return 'filament_plugin';
        }

        if (isset($require['laravel-zero/framework'])) {
            return 'laravel_zero_cli';
        }

        if ($vendor === 'cakephp' || in_array('cakephp', $topics, true)) {
            return 'cakephp_package';
        }

        if (in_array('livewire', $topics, true) && isset($require['livewire/livewire'])) {
            return 'livewire_package';
        }

        if (in_array('starter-kit', $topics, true)) {
            return 'starter_kit';
        }

        if (in_array('laravel', $topics, true) || isset($require['laravel/framework'])) {
            return 'laravel_package';
        }

        if (in_array('framework', $topics, true)) {
            return 'framework';
        }

        // Database engines win over Docker even when they ship a compose
        // file — a DB tagged `postgresql` + `docker` is primarily a
        // database. Checked before the docker branch for that reason.
        $databaseTopics = array_intersect(
            ['database', 'databases', 'dbms', 'rdbms', 'sql', 'nosql', 'newsql',
                'postgres', 'postgresql', 'mysql', 'mariadb', 'sqlite', 'redis',
                'mongodb', 'cassandra', 'clickhouse', 'cockroachdb', 'duckdb',
                'timescaledb', 'elasticsearch', 'opensearch', 'key-value-store'],
            $topics,
        );
        if ($databaseTopics !== []) {
            return 'database';
        }

        // Broader docker-topic match — self-hosted projects often tag
        // themselves with `selfhosted` / `self-hosted` / `containers`
        // rather than the bare `docker` topic.
        $dockerTopics = array_intersect(
            ['docker', 'dockerfile', 'docker-image', 'containers', 'selfhosted', 'self-hosted', 'self-hosting'],
            $topics,
        );
        if ($hasDockerCompose || $dockerTopics !== []) {
            return 'docker';
        }

        return 'tool';
    }

    /**
     * HEAD a raw.githubusercontent.com path to verify a file exists on a
     * given branch without downloading it. Used by the Docker detector
     * to check for docker-compose / compose files without parsing JSON.
     */
    private static function fileExists(string $repoSlug, string $branch, string $file): bool
    {
        try {
            $response = Http::timeout(6)
                ->withHeaders(['User-Agent' => 'jeffersongoncalves-site'])
                ->head("https://raw.githubusercontent.com/{$repoSlug}/{$branch}/{$file}");
        } catch (Throwable) {
            return false;
        }

        return $response->successful();
    }

    /**
     * @param  array<string, mixed>|null  $composer
     * @param  array<string, mixed>|null  $package
     * @return list<string>
     */
    private static function resolveStack(?array $composer, ?array $package): array
    {
        $stack = [];
        $require = is_array($composer['require'] ?? null) ? $composer['require'] : [];

        if (isset($require['laravel/framework'])) {
            $stack[] = 'Laravel';
        }
        if (self::hasAnyKey($require, 'filament/')) {
            $stack[] = 'Filament';
        }
        if (isset($require['livewire/livewire'])) {
            $stack[] = 'Livewire';
        }

        $deps = array_merge(
            is_array($package['dependencies'] ?? null) ? $package['dependencies'] : [],
            is_array($package['devDependencies'] ?? null) ? $package['devDependencies'] : [],
        );

        if (isset($deps['tailwindcss'])) {
            $stack[] = 'Tailwind';
        }
        if (isset($deps['alpinejs'])) {
            $stack[] = 'Alpine.js';
        }
        if (isset($deps['vue'])) {
            $stack[] = 'Vue';
        }
        if (isset($deps['react'])) {
            $stack[] = 'React';
        }

        return array_values(array_unique($stack));
    }

    /**
     * Resolve supported Filament versions from `composer.require['filament/filament']`
     * and verify each major has a matching `{major}.x` branch (or that
     * `{major}` matches the default branch). Non-Filament-plugin projects
     * return an empty list — versions there are editor-managed.
     *
     * @param  array<string, mixed>|null  $composer
     * @param  list<string>  $branches
     * @return list<string>
     */
    private static function resolveVersions(?array $composer, array $branches, string $category): array
    {
        if ($category !== 'filament_plugin') {
            return [];
        }

        $constraint = $composer['require']['filament/filament'] ?? null;

        if (! is_string($constraint)) {
            return [];
        }

        $versions = [];

        if (preg_match_all('/(\d+)/', $constraint, $m)) {
            foreach ($m[1] as $major) {
                if ((int) $major >= 3 && (int) $major <= 9) {
                    $versions[] = 'v'.$major;
                }
            }
        }

        return array_values(array_unique($versions));
    }

    /**
     * @param  array<string, mixed>|null  $composer
     * @param  array<string, mixed>|null  $package
     * @param  array<string, mixed>  $repo
     */
    private static function pickDescription(?array $composer, ?array $package, array $repo): ?string
    {
        foreach ([$composer['description'] ?? null, $package['description'] ?? null, $repo['description'] ?? null] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $composer
     */
    private static function buildPackagistUrl(?array $composer): ?string
    {
        $name = $composer['name'] ?? null;

        if (! is_string($name) || ! preg_match('#^[a-z0-9_.-]+/[a-z0-9_.-]+$#i', $name)) {
            return null;
        }

        return 'https://packagist.org/packages/'.strtolower($name);
    }

    /**
     * @param  array<string, mixed>  $arr
     */
    private static function hasAnyKey(array $arr, string $prefix): bool
    {
        foreach (array_keys($arr) as $key) {
            if (str_starts_with((string) $key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function prettifyName(string $repo): string
    {
        $name = ucwords(str_replace(['-', '_'], ' ', $repo));

        return preg_replace('/\bPhp\b/u', 'PHP', $name) ?? $name;
    }
}

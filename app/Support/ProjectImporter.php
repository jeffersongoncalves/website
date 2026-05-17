<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
        $branches = self::fetchBranches($repoSlug);

        [$owner, $repoName] = explode('/', $repoSlug, 2);

        $category = self::resolveCategory($composer, $repo);
        $packageType = self::resolvePackageType($composer, $package, $repo);

        $fields = [
            'github_url' => $url,
            'slug' => $owner.'-'.$repoName,
            'name' => self::prettifyName($repoName),
            'repo' => $repoName,
            'license' => is_string($repo['license']['spdx_id'] ?? null) ? $repo['license']['spdx_id'] : 'MIT',
            'readme_branch' => $branch,
            'docs_url' => self::nullableString($repo['homepage'] ?? null),
            'title.en' => self::pickDescription($composer, $package, $repo),
            'category' => $category,
            'package_type' => $packageType,
            'packagist_url' => self::buildPackagistUrl($composer),
            'npm_url' => self::buildNpmUrl($package),
            'stack' => self::resolveStack($composer, $package),
            'versions' => self::resolveVersions($composer, $branches, $category),
        ];

        $warnings = [];

        if ($category === 'tool' && ! isset($composer['type'])) {
            $warnings[] = 'category_fallback';
        }

        if ($fields['title.en'] === null) {
            $warnings[] = 'no_description';
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
    private static function resolvePackageType(?array $composer, ?array $package, array $repo): string
    {
        if ($composer !== null) {
            return 'composer';
        }

        if ($package !== null) {
            return 'npm';
        }

        $homepage = is_string($repo['homepage'] ?? null) ? (string) $repo['homepage'] : '';
        if ($homepage !== '' && str_contains($homepage, 'plugins.jetbrains.com')) {
            return 'jetbrains';
        }

        return 'none';
    }

    /**
     * Priority order matters — a Filament plugin that also requires Laravel
     * should still resolve as a filament_plugin, not laravel_package.
     *
     * @param  array<string, mixed>|null  $composer
     * @param  array<string, mixed>  $repo
     */
    private static function resolveCategory(?array $composer, array $repo): string
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

        return 'tool';
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
     * @param  array<string, mixed>|null  $package
     */
    private static function buildNpmUrl(?array $package): ?string
    {
        $name = $package['name'] ?? null;

        if (! is_string($name) || ! preg_match('#^(@[a-z0-9_.~-]+/)?[a-z0-9_.~-]+$#i', $name)) {
            return null;
        }

        return 'https://www.npmjs.com/package/'.$name;
    }

    /**
     * @param  array<string, mixed>  $arr
     */
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

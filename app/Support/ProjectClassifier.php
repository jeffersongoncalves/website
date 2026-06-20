<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Topic/name-based category heuristics shared by the periodic re-classification
 * path. Given a repo's GitHub topics + name, return a more specific category
 * value, or null when nothing matches. Used to upgrade generic `application` /
 * `tool` rows on every metrics sync as a repo's topics evolve — it never needs
 * a composer.json/package.json, only the data already on the /repos response.
 *
 * Priority is most-specific-first: an `awesome-laravel` repo (topic `laravel` +
 * `awesome-` name) resolves to awesome_list, not laravel_package.
 */
class ProjectClassifier
{
    /**
     * @param  list<string>  $topics
     */
    public static function specificFromTopics(array $topics, string $repoName): ?string
    {
        $topics = array_map(static fn ($t): string => strtolower((string) $t), $topics);
        $name = strtolower($repoName);

        if (str_starts_with($name, 'awesome-')
            || array_intersect(['awesome', 'awesome-list', 'awesome-lists'], $topics) !== []) {
            return 'awesome_list';
        }

        if (in_array('filament-plugin', $topics, true)) {
            return 'filament_plugin';
        }

        if (in_array('starter-kit', $topics, true)) {
            return 'starter_kit';
        }

        if (array_intersect(['css-framework', 'css-frameworks'], $topics) !== []) {
            return 'css_framework';
        }

        if (array_intersect(
            ['android', 'ios', 'kotlin', 'swift', 'swiftui', 'jetpack-compose',
                'flutter', 'react-native', 'android-library', 'ios-library'],
            $topics,
        ) !== []) {
            return 'mobile_library';
        }

        if (array_intersect(
            ['tutorial', 'tutorials', 'education', 'educational', 'learning',
                'course', 'courses', 'roadmap', 'roadmaps', 'book', 'books',
                'curriculum', 'cheatsheet', 'cheatsheets', 'interview',
                'interview-questions', 'study'],
            $topics,
        ) !== []) {
            return 'learning_resource';
        }

        if (array_intersect(
            ['database', 'databases', 'dbms', 'rdbms', 'sql', 'nosql', 'newsql',
                'postgres', 'postgresql', 'mysql', 'mariadb', 'sqlite', 'redis',
                'mongodb', 'cassandra', 'clickhouse', 'cockroachdb', 'duckdb',
                'timescaledb', 'elasticsearch', 'opensearch', 'key-value-store'],
            $topics,
        ) !== []) {
            return 'database';
        }

        if (array_intersect(
            ['docker', 'dockerfile', 'docker-image', 'containers', 'selfhosted', 'self-hosted', 'self-hosting'],
            $topics,
        ) !== []) {
            return 'docker';
        }

        if (in_array('framework', $topics, true)) {
            return 'framework';
        }

        // Deliberately no bare `laravel` topic → laravel_package rule here.
        // This path has no composer.json to confirm a PHP package, and a real
        // Laravel package always imports as laravel_package via its composer
        // manifest (so it never lands in the generic application/tool bucket
        // this method upgrades). A `laravel` topic alone only ever appears on
        // false positives — multi-framework JS/TS projects that merely support
        // Laravel (e.g. shadcn-ui/ui).
        return null;
    }

    /**
     * Full classification from a fetched repo + its composer.json — the import
     * path's richer counterpart to specificFromTopics, which only sees topics.
     * Composer signals (type, require, vendor) take priority, then topics, with
     * `tool` as the catch-all the importer later splits by package_type.
     *
     * @param  array<string, mixed>|null  $composer
     * @param  array<string, mixed>  $repo
     */
    public static function category(?array $composer, array $repo, bool $hasDockerCompose = false): string
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

        // A bare `laravel` topic is not enough — multi-framework JS/TS
        // projects (e.g. shadcn-ui/ui) tag `laravel` because they *support*
        // Laravel, not because they're a PHP package. Require a composer
        // manifest (the `laravel/framework` require already implies one).
        if (isset($require['laravel/framework'])
            || (in_array('laravel', $topics, true) && $composer !== null)) {
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

        $repoName = is_string($repo['name'] ?? null) ? strtolower((string) $repo['name']) : '';

        // Awesome lists — the de-facto convention is an `awesome-` repo name
        // or an `awesome` / `awesome-list` topic.
        if (str_starts_with($repoName, 'awesome-')
            || array_intersect(['awesome', 'awesome-list', 'awesome-lists'], $topics) !== []) {
            return 'awesome_list';
        }

        // CSS frameworks — topic-driven so a PHP/JS lib that merely ships a
        // stylesheet isn't misfiled here.
        if (array_intersect(['css-framework', 'css-frameworks'], $topics) !== []) {
            return 'css_framework';
        }

        // Mobile libraries / SDKs — platform topics are the reliable signal.
        if (array_intersect(
            ['android', 'ios', 'kotlin', 'swift', 'swiftui', 'jetpack-compose',
                'flutter', 'react-native', 'android-library', 'ios-library'],
            $topics,
        ) !== []) {
            return 'mobile_library';
        }

        // Learning resources — roadmaps, courses, books, cheatsheets.
        if (array_intersect(
            ['tutorial', 'tutorials', 'education', 'educational', 'learning',
                'course', 'courses', 'roadmap', 'roadmaps', 'book', 'books',
                'curriculum', 'cheatsheet', 'cheatsheets', 'interview',
                'interview-questions', 'study'],
            $topics,
        ) !== []) {
            return 'learning_resource';
        }

        return 'tool';
    }

    /**
     * Detected tech stack from the composer require + npm dependencies.
     *
     * @param  array<string, mixed>|null  $composer
     * @param  array<string, mixed>|null  $package
     * @return list<string>
     */
    public static function stack(?array $composer, ?array $package): array
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
     * Resolve supported Filament versions from `composer.require['filament/filament']`.
     * Non-Filament-plugin projects return an empty list — versions there are
     * editor-managed.
     *
     * @param  array<string, mixed>|null  $composer
     * @param  list<string>  $branches
     * @return list<string>
     */
    public static function versions(?array $composer, array $branches, string $category): array
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
     * @param  array<array-key, mixed>  $arr
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
}

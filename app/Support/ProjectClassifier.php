<?php

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
}

<?php

namespace App\Support;

use App\Models\Project;

class ProjectMatcher
{
    /**
     * Locate a project that already represents the source being imported, so a
     * second pass over the same repo/package updates the row instead of
     * creating a duplicate (and a `-1`/`-2` slug suffix).
     *
     * @param  array<string, mixed>  $attrs
     */
    public static function findExisting(string $source, array $attrs): ?Project
    {
        if ($source === 'github') {
            return self::findByGithubUrl(self::stringOrNull($attrs['github_url'] ?? null));
        }

        if ($source === 'npm') {
            $npmUrl = self::stringOrNull($attrs['npm_url'] ?? null);
            if ($npmUrl !== null) {
                $byNpm = Project::query()->where('npm_url', $npmUrl)->first();
                if ($byNpm !== null) {
                    return $byNpm;
                }
            }

            // Fallback: npm import for a repo already cadastrado via GitHub.
            // Only match against root repos — a `/tree/branch/dir` URL means
            // the npm package is a monorepo subtree and is a distinct project
            // from the repo root.
            $githubUrl = self::stringOrNull($attrs['github_url'] ?? null);
            if ($githubUrl !== null && ! str_contains($githubUrl, '/tree/')) {
                return self::findByGithubUrl($githubUrl);
            }

            return null;
        }

        // youtube + url imports both populate docs_url with the canonical link.
        $docsUrl = self::stringOrNull($attrs['docs_url'] ?? null);
        if ($docsUrl !== null) {
            return Project::query()->where('docs_url', $docsUrl)->first();
        }

        return null;
    }

    public static function findByGithubUrl(?string $url): ?Project
    {
        $slug = GithubReadme::repoFromUrl($url);

        if ($slug === null) {
            return null;
        }

        return Project::query()
            ->whereNotNull('github_url')
            ->get()
            ->first(fn (Project $p) => GithubReadme::repoFromUrl($p->github_url) === $slug);
    }

    /**
     * Copy importer attributes onto an existing project, but only for fields
     * the editor hasn't already populated. Mirrors HasImportFromGithubAction's
     * "fill empty, skip the rest" rule so manual edits survive a re-import.
     *
     * @param  array<string, mixed>  $attrs
     * @return list<string>
     */
    public static function fillMissing(Project $project, array $attrs): array
    {
        $changes = [];

        foreach ($attrs as $key => $value) {
            if ($key === 'slug') {
                continue;
            }

            $current = $project->getAttribute($key);

            if (! self::isEmptyValue($current)) {
                continue;
            }

            $project->setAttribute($key, $value);
            $changes[] = $key;
        }

        return $changes;
    }

    private static function isEmptyValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}

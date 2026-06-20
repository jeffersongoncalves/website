<?php

declare(strict_types=1);

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
            $byGithub = self::findByGithubUrl(self::stringOrNull($attrs['github_url'] ?? null));
            if ($byGithub !== null) {
                return $byGithub;
            }

            // Cross-source fallback: same project may already exist as a
            // website seed (only docs_url populated) — match by canonical
            // docs_url to collapse the two rows.
            return self::findByDocsUrl(self::stringOrNull($attrs['docs_url'] ?? null));
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
                $byRepo = self::findByGithubUrl($githubUrl);
                if ($byRepo !== null) {
                    return $byRepo;
                }
            }

            return self::findByDocsUrl(self::stringOrNull($attrs['docs_url'] ?? null));
        }

        // youtube + url imports both populate docs_url with the canonical link.
        return self::findByDocsUrl(self::stringOrNull($attrs['docs_url'] ?? null));
    }

    /**
     * Match against `docs_url` ignoring trailing slash. Website seeds tend to
     * keep the canonical trailing slash (`https://example.com/`) while the
     * GitHub importer strips it — both spellings refer to the same site.
     */
    public static function findByDocsUrl(?string $url): ?Project
    {
        if ($url === null) {
            return null;
        }

        $variants = array_unique([$url, rtrim($url, '/'), rtrim($url, '/').'/']);

        return Project::query()->whereIn('docs_url', $variants)->first();
    }

    public static function findByGithubUrl(?string $url): ?Project
    {
        $slug = GithubReadme::repoFromUrl($url);

        if ($slug === null) {
            return null;
        }

        // Narrow to rows whose URL contains this owner/repo (case-insensitive)
        // instead of loading the whole github catalogue into memory — this runs
        // on every import job's pre-check, so an awesome-list import of thousands
        // of repos would otherwise re-scan the table once per job. The strcasecmp
        // callback below is still the authoritative match (handles URL casing /
        // `/tree/branch` variants the LIKE can't distinguish).
        return Project::query()
            ->whereNotNull('github_url')
            ->whereRaw('LOWER(github_url) LIKE ?', ['%'.strtolower($slug).'%'])
            ->get()
            ->first(fn (Project $p) => strcasecmp(
                (string) GithubReadme::repoFromUrl($p->github_url),
                $slug
            ) === 0);
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

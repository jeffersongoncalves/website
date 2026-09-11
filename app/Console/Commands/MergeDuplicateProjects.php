<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectSlugAlias;
use App\Support\GithubQuota;
use App\Support\GithubReadme;
use App\Support\ProjectMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JeffersonGoncalves\GitHubClient\Exceptions\GitHubRateLimitException;
use JeffersonGoncalves\GitHubClient\GitHubClient;

/**
 * Collapses rows that share a github_repo_id (see
 * BackfillGithubRepoIdJob — must run first, this only groups rows that
 * already have the id filled in). Keeps the oldest row per group, merges
 * whatever the other row(s) have that the keeper doesn't, and 301s the
 * dropped row's old slug at the keeper via project_slug_aliases.
 *
 * Fields never copied from a loser onto the keeper via the generic
 * fill-if-empty pass: id, slug (HasSlug regenerates it), github_url/
 * github_repo_id (re-verified separately, see resolveCanonicalGithubUrl()),
 * created_at/updated_at, starred_at (merged as MIN across the group), and
 * METRIC_FIELDS (merged from whichever row has the freshest last_synced_at,
 * not fill-if-empty — a stale keeper's old stars/downloads shouldn't win
 * over a loser that was synced more recently).
 */
class MergeDuplicateProjects extends Command
{
    protected $signature = 'projects:merge-duplicate-repos {--dry-run : Print the merge plan without writing anything}';

    protected $description = 'Merge project rows that share a github_repo_id, keeping the oldest and preserving the others\' old slugs as redirects';

    /**
     * Everything ProjectMetrics::sync() itself writes — auto-derived data,
     * not an editor's manual input, so "freshest wins" is correct here even
     * though it's the opposite rule from every other field's fill-if-empty.
     */
    private const METRIC_FIELDS = [
        'stars', 'downloads', 'downloads_label', 'user_contributions',
        'language', 'category', 'topics', 'has_branches', 'branch_overrides',
        'packagist_url', 'npm_url', 'is_maintainer', 'is_daily_driver',
        'last_synced_at',
    ];

    private const NEVER_COPY = [
        'id', 'slug', 'github_url', 'github_repo_id', 'starred_at', 'created_at', 'updated_at',
        ...self::METRIC_FIELDS,
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $duplicateIds = Project::query()
            ->whereNotNull('github_repo_id')
            ->select('github_repo_id')
            ->groupBy('github_repo_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('github_repo_id');

        if ($duplicateIds->isEmpty()) {
            $this->info('No duplicate github_repo_id groups found.');

            return self::SUCCESS;
        }

        foreach ($duplicateIds as $repoId) {
            $this->mergeGroup($repoId, $dryRun);
        }

        if ($dryRun) {
            $this->warn('Dry run — nothing was written. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    private function mergeGroup(int $repoId, bool $dryRun): void
    {
        /** @var Collection<int, Project> $rows */
        $rows = Project::query()->where('github_repo_id', $repoId)->oldest('created_at')->get();

        $keeper = $rows->shift();

        if ($keeper === null) {
            return;
        }

        $all = $rows->concat([$keeper]);

        $this->line("github_repo_id={$repoId}: keep [{$keeper->slug}] (#{$keeper->getKey()}) — slug kept: {$keeper->slug}");

        // Re-verify the canonical URL straight from GitHub (by the keeper's
        // current slug — GitHub 301s a stale/renamed slug transparently) —
        // don't just trust every row in the group was already backfilled.
        // resolveCanonicalGithubUrl() already lowercases — an exact (not
        // case-insensitive) compare here also catches a keeper whose own
        // URL is the right repo but the wrong casing.
        $canonicalUrl = $this->resolveCanonicalGithubUrl($keeper);
        if ($canonicalUrl !== null && $keeper->github_url !== $canonicalUrl) {
            $this->line("  github_url: {$keeper->github_url} -> {$canonicalUrl}");
            $keeper->github_url = $canonicalUrl;
        } else {
            $this->line("  github_url: {$keeper->github_url} (already canonical)");
        }

        $freshest = $all->sortByDesc(fn (Project $p): int => $p->last_synced_at === null ? -1 : (int) $p->last_synced_at->timestamp)->first();

        if ($freshest !== null && ! $freshest->is($keeper)) {
            $metricsChanged = [];

            foreach (self::METRIC_FIELDS as $field) {
                if ($keeper->getAttribute($field) !== $freshest->getAttribute($field)) {
                    $keeper->setAttribute($field, $freshest->getAttribute($field));
                    $metricsChanged[] = $field;
                }
            }

            if ($metricsChanged !== []) {
                $this->line("  metrics from [{$freshest->slug}] (freshest last_synced_at): ".implode(', ', $metricsChanged));
            }
        }

        $earliestStarredAt = $keeper->starred_at;

        foreach ($rows as $loser) {
            $this->line("  merge  [{$loser->slug}] (#{$loser->getKey()}) into keeper");

            if ($loser->starred_at !== null && ($earliestStarredAt === null || $loser->starred_at->lt($earliestStarredAt))) {
                $earliestStarredAt = $loser->starred_at;
                $this->line("    starred_at -> {$earliestStarredAt->toIso8601String()} (from loser)");
            }

            $attrs = collect($loser->getAttributes())
                ->except(self::NEVER_COPY)
                ->all();
            // fillMissing() records a field as "filled" whenever the
            // keeper's own value was empty, even if the loser's value was
            // ALSO empty (a null-to-null no-op) — filter those out so the
            // diff only reports fields the loser genuinely contributed.
            $filled = array_values(array_filter(
                ProjectMatcher::fillMissing($keeper, $attrs),
                fn (string $field): bool => ! self::isEmptyForDiff($keeper->getAttribute($field))
            ));

            // featured is a boolean flag, not fill-if-empty data — fillMissing
            // treats false as "already set" (correctly, for most fields) and
            // would never let a loser's manually-set true win. It should here.
            if ($loser->featured && ! $keeper->featured) {
                $keeper->featured = true;
                $filled[] = 'featured';
            }

            $this->line('    field diff: '.($filled !== [] ? implode(', ', $filled) : '(none — keeper already had everything)'));
            $this->line("    alias created: {$loser->slug} -> project #{$keeper->getKey()}");
        }

        if ($dryRun) {
            return;
        }

        DB::transaction(function () use ($keeper, $rows, $earliestStarredAt): void {
            $keeper->starred_at = $earliestStarredAt;
            $keeper->saveQuietly();

            foreach ($rows as $loser) {
                // Re-point any alias the loser itself already owned (it was
                // some earlier merge's redirect) before the row disappears.
                ProjectSlugAlias::query()->where('project_id', $loser->getKey())->update(['project_id' => $keeper->getKey()]);

                // 301 the loser's own slug at the keeper, unless that slug
                // is already claimed (keeper's own slug, or a prior alias).
                if ($loser->slug !== $keeper->slug && ! ProjectSlugAlias::query()->where('slug', $loser->slug)->exists()) {
                    ProjectSlugAlias::query()->create(['project_id' => $keeper->getKey(), 'slug' => $loser->slug]);
                }

                $loser->delete();
            }
        });

        $this->info("  merged {$rows->count()} row(s) into #{$keeper->getKey()}");
    }

    /**
     * Re-fetches the repo by the keeper's current slug — GitHub 301s a
     * stale/renamed slug transparently, so this is the definitive canonical
     * html_url regardless of whether BackfillGithubRepoIdJob already ran
     * cleanly for this row. Returns null (keeper's current value is kept
     * unchanged) on a rate limit or an unparseable/unresolvable URL — a
     * one-off re-verification isn't worth blocking the whole merge run over.
     */
    private function resolveCanonicalGithubUrl(Project $keeper): ?string
    {
        $slug = GithubReadme::repoFromUrl($keeper->github_url);

        if ($slug === null) {
            return null;
        }

        $delay = GithubQuota::reserve(1);
        if ($delay > 0) {
            sleep($delay);
        }

        try {
            $repo = GitHubClient::fetchRepo($slug);
        } catch (GitHubRateLimitException) {
            $this->warn('    could not re-verify canonical github_url (rate limited) — keeping current value');

            return null;
        }

        $htmlUrl = $repo['html_url'] ?? null;

        return is_string($htmlUrl) && $htmlUrl !== '' ? strtolower($htmlUrl) : null;
    }

    /** Mirrors ProjectMatcher's private isEmptyValue() — same "empty" definition. */
    private static function isEmptyForDiff(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}

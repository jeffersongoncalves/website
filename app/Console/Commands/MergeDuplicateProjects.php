<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectSlugAlias;
use App\Support\ProjectMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Collapses rows that share a github_repo_id (see
 * BackfillGithubRepoIdJob — must run first, this only groups rows that
 * already have the id filled in). Keeps the oldest row per group, merges
 * whatever the newer row(s) have that the keeper doesn't, and 301s the
 * dropped row's old slug at the keeper via project_slug_aliases.
 *
 * Fields never copied from a loser onto the keeper: id, slug (HasSlug
 * regenerates it), github_url/github_repo_id (already identical across the
 * group post-backfill), created_at/updated_at, and starred_at (merged
 * separately as MIN, not fill-if-empty).
 */
class MergeDuplicateProjects extends Command
{
    protected $signature = 'projects:merge-duplicate-repos {--dry-run : Print the merge plan without writing anything}';

    protected $description = 'Merge project rows that share a github_repo_id, keeping the oldest and preserving the others\' old slugs as redirects';

    private const NEVER_COPY = ['id', 'slug', 'github_url', 'github_repo_id', 'starred_at', 'created_at', 'updated_at'];

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
        $this->line("github_repo_id={$repoId}: keep [{$keeper->slug}] (#{$keeper->getKey()})");

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
            $filled = ProjectMatcher::fillMissing($keeper, $attrs);

            // featured is a boolean flag, not fill-if-empty data — fillMissing
            // treats false as "already set" (correctly, for most fields) and
            // would never let a loser's manually-set true win. It should here.
            if ($loser->featured && ! $keeper->featured) {
                $keeper->featured = true;
                $filled[] = 'featured';
            }

            if ($filled !== []) {
                $this->line('    fields filled on keeper: '.implode(', ', $filled));
            }

            $this->line("    alias  {$loser->slug} -> project #{$keeper->getKey()}");
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
}

<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Project;
use App\Support\GithubReadme;
use App\Support\ProjectClassifier;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use JeffersonGoncalves\GitHubClient\GitHubClient;
use Throwable;

/**
 * One-off backfill (dispatched by the reconcile-multibranch-plugin-versions
 * operation): re-derive `versions` for one already-cadastrado multi-branch
 * Filament plugin by fetching composer.json from every N.x branch and
 * unioning each branch's `filament/filament` constraint — the same logic
 * ProjectImporter::resolveVersions() now applies at import time (see
 * commit 6c8b9e9), applied here to a row the old default-branch-only import
 * path left incomplete.
 *
 * Promote only: a version already stored is never removed, only versions
 * missing from a real branch are added — mirrors the "promote only, never
 * demote" rule ProjectMetrics::repairBranchOverrides() already uses for the
 * same reason (a transient fetch gap must not erase real, previously-seen
 * data).
 */
class ReconcileFilamentPluginVersionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 0;

    public function __construct(public int $projectId)
    {
        $this->onQueue('github')->onConnection('redis-github');
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(2);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("reconcile-versions:{$this->projectId}"))->dontRelease(),
            new RateLimited('github-api'),
        ];
    }

    public function handle(): void
    {
        $project = Project::query()->find($this->projectId);

        if ($project === null || $project->github_url === null) {
            return;
        }

        $slug = GithubReadme::repoFromUrl((string) $project->github_url);

        if ($slug === null) {
            return;
        }

        $category = $project->category->value;
        $branches = GitHubClient::fetchBranches($slug);

        if (! ProjectClassifier::hasVersionBranches($branches, $category)) {
            return;
        }

        $discovered = [];
        foreach ($branches as $branchName) {
            if (preg_match('/^\d+\.x$/', $branchName) !== 1) {
                continue;
            }

            $composer = GitHubClient::fetchManifest($slug, $branchName, 'composer.json');
            $discovered = [...$discovered, ...ProjectClassifier::versions($composer, $branches, $category)];
        }

        $current = is_array($project->versions) ? $project->versions : [];
        $merged = array_values(array_unique([...$current, ...$discovered]));
        sort($merged);

        $sortedCurrent = $current;
        sort($sortedCurrent);

        if ($merged === $sortedCurrent && $project->has_branches) {
            return;
        }

        $project->versions = $merged;
        $project->has_branches = true;
        // Quiet: this backfill can touch ~90 rows in one sweep — avoid
        // flushing the site-wide page cache once per row (see
        // ProjectMetrics::sync()'s identical saveQuietly() rationale).
        $project->saveQuietly();

        Log::info('ReconcileFilamentPluginVersionsJob: updated versions', [
            'slug' => $project->slug,
            'from' => $current,
            'to' => $merged,
        ]);
    }

    public function failed(?Throwable $e): void
    {
        Log::error('ReconcileFilamentPluginVersionsJob failed', [
            'project_id' => $this->projectId,
            'error' => $e?->getMessage(),
        ]);
    }
}

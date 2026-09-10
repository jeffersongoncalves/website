<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\BackfillGithubRepoIdJob;
use App\Models\Project;
use Illuminate\Console\Command;

/**
 * One-time backfill for the github_repo_id dedup column (see the
 * 2026_09_10_120000_add_github_repo_id_to_projects_table migration and
 * MergeDuplicateProjects). Re-runnable: only targets rows still missing the
 * id, so an interrupted run just picks up where it left off.
 */
class BackfillGithubRepoId extends Command
{
    protected $signature = 'projects:backfill-github-repo-id';

    protected $description = 'Dispatch jobs to resolve each project\'s numeric github_repo_id';

    public function handle(): int
    {
        $projects = Project::query()
            ->whereNotNull('github_url')
            ->whereNull('github_repo_id')
            ->whereNull('unavailable_at')
            ->get();

        foreach ($projects as $project) {
            BackfillGithubRepoIdJob::enqueue($project);
        }

        $hours = round($projects->count() * BackfillGithubRepoIdJob::COST / 4500, 2);
        $this->info("Dispatched {$projects->count()} backfill jobs (~{$hours}h at the github-api budget).");

        return self::SUCCESS;
    }
}

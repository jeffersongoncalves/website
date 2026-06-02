<?php

namespace App\Observers;

use App\Enums\ProjectStatus;
use App\Http\Middleware\CachePublicPage;
use App\Jobs\GenerateSitemapJob;
use App\Jobs\RefreshProjectStatsJob;
use App\Jobs\SyncProjectMetricsJob;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\InvalidArgumentException;

class ProjectObserver
{
    public function saving(Project $project): void
    {
        // Stamp published_at the first time a project flips to Published.
        if ($project->status === ProjectStatus::Published && empty($project->published_at)) {
            $project->published_at = now();
        }
    }

    public function created(Project $project): void
    {
        SyncProjectMetricsJob::dispatch($project);

        $this->flush();
    }

    public function updated(Project $project): void
    {
        // Refresh GitHub/Packagist metrics + branch verification whenever the
        // editor touches a field that changes WHAT we should fetch. Metric-only
        // updates (stars, downloads, branch_overrides, last_synced_at) coming
        // from the job itself are excluded so we don't bounce-loop.
        if ($project->wasChanged(['repo', 'github_url', 'packagist_url', 'npm_url', 'docs_url', 'versions'])) {
            SyncProjectMetricsJob::dispatch($project);
        }

        $this->flush();
    }

    public function deleted(Project $project): void
    {
        $this->flush();
    }

    public function restored(Project $project): void
    {
        $this->flush();
    }

    public function forceDeleted(Project $project): void
    {
        $this->flush();
    }

    private function flush(): void
    {
        try {
            Cache::delete('projects_count');
            Cache::delete('featured_projects');
        } catch (InvalidArgumentException) {
        }

        // Invalidate the full-page response cache so edits surface immediately.
        CachePublicPage::flush();

        // Recompute the local-only columns of the SiteStat singleton so the
        // admin metrics widget + public landing cards reflect the change.
        // GitHub-sourced fields stay untouched — those refresh on the scheduled
        // sync. Dispatched (not run inline) and debounced via the job's
        // ShouldBeUniqueUntilProcessing lock + this delay, so a bulk import that
        // saves hundreds of rows collapses into ~one recompute instead of one
        // per row on the worker path.
        RefreshProjectStatsJob::dispatch()->delay(now()->addSeconds(10));

        GenerateSitemapJob::dispatch();
    }
}

<?php

namespace App\Observers;

use App\Enums\ProjectStatus;
use App\Jobs\GenerateSitemapJob;
use App\Jobs\SendProjectPublishedNotification;
use App\Jobs\SyncProjectMetricsJob;
use App\Jobs\TranslateProjectTitleJob;
use App\Models\Project;
use App\Support\SiteStats;
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
        TranslateProjectTitleJob::dispatch($project);

        // A project created directly as Published fires the push once.
        if ($project->status === ProjectStatus::Published) {
            SendProjectPublishedNotification::dispatch($project);
        }

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

        if ($project->wasChanged('title')) {
            TranslateProjectTitleJob::dispatch($project);
        }

        // Notify subscribers the first time a draft transitions to Published.
        // Keyed on the status column changing INTO Published — re-saving an
        // already-published project (or archived → published again) won't
        // re-fire because `wasChanged('status')` is false on a no-op, and the
        // archived→published edge is rare enough to accept a repeat push.
        if ($project->wasChanged('status') && $project->status === ProjectStatus::Published) {
            SendProjectPublishedNotification::dispatch($project);
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

        // Recompute the local-only columns of the SiteStat singleton so the
        // admin metrics widget + public landing cards reflect the change
        // immediately. GitHub-sourced fields stay untouched — those refresh
        // on the scheduled sync.
        SiteStats::refreshProjectDerived();

        GenerateSitemapJob::dispatch();
    }
}

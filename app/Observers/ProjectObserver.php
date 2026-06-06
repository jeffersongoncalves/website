<?php

namespace App\Observers;

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Site\LlmsTxtController;
use App\Http\Middleware\CachePublicPage;
use App\Jobs\GenerateSitemapJob;
use App\Jobs\RefreshProjectStatsJob;
use App\Jobs\SyncProjectMetricsJob;
use App\Livewire\Site\LinksPage;
use App\Livewire\Site\LinksSection;
use App\Models\Project;
use App\Support\GithubReadme;
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

        // Keep the denormalised, indexed owner login in sync with github_url so
        // the authored/owned facets stay an exact-match index lookup.
        if ($project->isDirty('github_url')) {
            $slug = GithubReadme::repoFromUrl($project->github_url);

            $project->github_owner = $slug !== null
                ? strtolower(explode('/', $slug, 2)[0])
                : null;
        }
    }

    public function created(Project $project): void
    {
        SyncProjectMetricsJob::dispatch($project);

        $this->flush(featuredAffected: (bool) $project->featured);
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

        // The landing featured list only changes when the row is (or just
        // stopped being) featured — editing an ordinary project leaves it
        // untouched, so don't bust that cache for nothing.
        $this->flush(featuredAffected: (bool) $project->featured || $project->wasChanged('featured'));
    }

    public function deleted(Project $project): void
    {
        $this->flush(featuredAffected: (bool) $project->featured);
    }

    public function restored(Project $project): void
    {
        $this->flush(featuredAffected: (bool) $project->featured);
    }

    public function forceDeleted(Project $project): void
    {
        $this->flush(featuredAffected: (bool) $project->featured);
    }

    private function flush(bool $featuredAffected = true): void
    {
        try {
            Cache::delete('projects_count');

            if ($featuredAffected) {
                Cache::delete('featured_projects');
            }

            Cache::delete(LlmsTxtController::CACHE_KEY);

            // /links hub: which sections are non-empty + each section's topic
            // chips. Both derive purely from published external-link rows.
            Cache::delete(LinksPage::SECTIONS_CACHE_KEY);

            foreach (ProjectCategory::externalLinkCases() as $cat) {
                Cache::delete(LinksSection::topicsCacheKey($cat));
            }
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

<?php

namespace App\Observers;

use App\Enums\ProjectStatus;
use App\Jobs\GenerateSitemapJob;
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
        $this->flush();
    }

    public function updated(Project $project): void
    {
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

        GenerateSitemapJob::dispatch();
    }
}

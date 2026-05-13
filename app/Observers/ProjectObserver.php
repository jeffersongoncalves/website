<?php

namespace App\Observers;

use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\InvalidArgumentException;

class ProjectObserver
{
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
    }
}

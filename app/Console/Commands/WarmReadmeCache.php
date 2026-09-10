<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\WarmReadmeCacheJob;
use App\Models\Project;
use Illuminate\Console\Command;

class WarmReadmeCache extends Command
{
    protected $signature = 'projects:warm-readme-cache {--slug= : Warm only a specific project slug}';

    protected $description = 'Dispatch jobs to pre-fetch and cache each project\'s README';

    public function handle(): int
    {
        $query = Project::query()->published()
            ->where(function ($q): void {
                $q->whereNotNull('github_url')->orWhereNotNull('npm_url');
            });

        if ($slug = $this->option('slug')) {
            $query->where('slug', $slug);
        }

        $projects = $query->get();

        foreach ($projects as $project) {
            WarmReadmeCacheJob::enqueue($project);
        }

        $this->info("Dispatched {$projects->count()} README warm jobs.");

        return self::SUCCESS;
    }
}

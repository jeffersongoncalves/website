<?php

namespace App\Console\Commands;

use App\Jobs\PersistSiteStatsJob;
use App\Jobs\SyncProjectMetricsJob;
use App\Models\Project;
use Illuminate\Console\Command;

class SyncProjectMetrics extends Command
{
    protected $signature = 'projects:sync-metrics {--slug= : Sync only a specific project slug}';

    protected $description = 'Dispatch jobs to sync stars and downloads from GitHub and Packagist';

    public function handle(): int
    {
        $query = Project::query()->published();

        if ($slug = $this->option('slug')) {
            $query->where('slug', $slug);
        }

        $projects = $query->get();

        foreach ($projects as $project) {
            SyncProjectMetricsJob::dispatch($project);
        }

        PersistSiteStatsJob::dispatch();

        $this->info("Dispatched {$projects->count()} project sync jobs + site stats job on the `github` queue.");

        return self::SUCCESS;
    }
}

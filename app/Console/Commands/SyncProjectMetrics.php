<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Support\ProjectMetrics;
use App\Support\SiteStats;
use Illuminate\Console\Command;

class SyncProjectMetrics extends Command
{
    protected $signature = 'projects:sync-metrics {--slug= : Sync only a specific project slug}';

    protected $description = 'Sync stars and downloads from GitHub and Packagist';

    public function handle(): int
    {
        $query = Project::query()->published();

        if ($slug = $this->option('slug')) {
            $query->where('slug', $slug);
        }

        $projects = $query->get();
        $bar = $this->output->createProgressBar($projects->count());
        $bar->start();

        $changed = 0;

        foreach ($projects as $project) {
            try {
                if (ProjectMetrics::sync($project)) {
                    $changed++;
                }
            } catch (\Throwable $e) {
                $this->newLine();
                $this->warn("[{$project->slug}] {$e->getMessage()}");
            }

            $bar->advance();
            usleep(150_000); // 150ms throttle to respect rate limits
        }

        $bar->finish();
        $this->newLine();
        $this->info("Synced {$changed}/{$projects->count()} projects.");

        SiteStats::refresh();
        $this->info('Site stats cache refreshed.');

        return self::SUCCESS;
    }
}

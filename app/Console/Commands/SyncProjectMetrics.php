<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\PersistSiteStatsJob;
use App\Jobs\SyncProjectMetricsJob;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class SyncProjectMetrics extends Command
{
    protected $signature = 'projects:sync-metrics {--slug= : Sync only a specific project slug}';

    protected $description = 'Dispatch jobs to sync stars and downloads from GitHub and Packagist';

    public function handle(): int
    {
        $query = Project::query()->published();

        if ($slug = $this->option('slug')) {
            $query->where('slug', $slug);
        } else {
            // Full-catalogue runs (the daily schedule) skip anything synced
            // recently. Without this, every run re-dispatches the whole
            // catalogue regardless of whether yesterday's batch actually
            // finished draining through the 'github' queue's rate limit —
            // an ever-growing backlog where jobs near the tail exhaust their
            // 2h retryUntil before ever getting a real turn. `--slug` (an
            // explicit manual resync) always runs regardless of freshness.
            $query->where(function ($q): void {
                $q->whereNull('last_synced_at')->orWhere('last_synced_at', '<', now()->subHours(20));
            });
        }

        $projects = $query->get();

        if ($projects->isEmpty()) {
            PersistSiteStatsJob::dispatch();
            $this->info('No projects to sync; dispatched site stats job.');

            return self::SUCCESS;
        }

        // Batch the per-project syncs and only recompute the site-stats
        // aggregate AFTER they all finish — otherwise PersistSiteStatsJob runs
        // early (while rate-limited project jobs are still queued) and sums
        // stale stars/downloads.
        Bus::batch($projects->map(fn (Project $project): SyncProjectMetricsJob => new SyncProjectMetricsJob($project))->all())
            ->name('sync-project-metrics')
            ->onQueue('github')
            ->then(fn () => PersistSiteStatsJob::dispatch())
            ->dispatch();

        $this->info("Dispatched {$projects->count()} project sync jobs; site stats job runs after the batch completes.");

        return self::SUCCESS;
    }
}

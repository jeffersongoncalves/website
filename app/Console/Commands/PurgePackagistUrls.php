<?php

namespace App\Console\Commands;

use App\Jobs\PurgeMisattributedPackagistUrlJob;
use App\Models\Project;
use Illuminate\Console\Command;

class PurgePackagistUrls extends Command
{
    protected $signature = 'projects:purge-packagist';

    protected $description = 'Dispatch a job per project to drop packagist_url links that do not belong to the repo';

    public function handle(): int
    {
        $ids = Project::query()
            ->whereNotNull('packagist_url')
            ->whereNotNull('github_url')
            ->pluck('id');

        $ids->each(fn (int $id) => PurgeMisattributedPackagistUrlJob::dispatch($id));

        $this->info("Dispatched {$ids->count()} packagist verification job(s).");

        return self::SUCCESS;
    }
}

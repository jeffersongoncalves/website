<?php

namespace App\Console\Commands;

use App\Jobs\SyncStarredReposJob;
use Illuminate\Console\Command;

class SyncStarredRepos extends Command
{
    protected $signature = 'projects:sync-stars {--full : Ignore the cursor and re-scan the full starred history}';

    protected $description = 'Dispatch a job to import newly-starred GitHub repos as draft projects';

    public function handle(): int
    {
        SyncStarredReposJob::dispatch((bool) $this->option('full'));

        $this->info('Dispatched star sync on the `github` queue.');

        return self::SUCCESS;
    }
}

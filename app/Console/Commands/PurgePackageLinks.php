<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\PurgeMisattributedPackageLinksJob;
use App\Models\Project;
use Illuminate\Console\Command;

class PurgePackageLinks extends Command
{
    protected $signature = 'projects:purge-package-links';

    protected $description = 'Dispatch a job per project to drop packagist_url / npm_url links that do not belong to the repo';

    public function handle(): int
    {
        $ids = Project::query()
            ->whereNotNull('github_url')
            ->where(fn ($q) => $q->whereNotNull('packagist_url')->orWhereNotNull('npm_url'))
            ->pluck('id');

        $ids->each(fn (int $id) => PurgeMisattributedPackageLinksJob::dispatch($id));

        $this->info("Dispatched {$ids->count()} package-link verification job(s).");

        return self::SUCCESS;
    }
}

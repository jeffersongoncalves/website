<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncPluginsJsonJob;
use Illuminate\Console\Command;

/**
 * Manual trigger for the same scan POST /api/plugins-sync queues — walks
 * every category in jeffersongoncalves/jeffersongoncalves's plugins.json
 * (startkit, filament, laravel, cli, jetbrains, vscode, cakephp, ...) and
 * dispatches one ImportGithubRepoJob per entry. Already-cadastrado repos
 * no-op cheaply, so this is safe to run any time to pick up new entries.
 */
class SyncPluginsJson extends Command
{
    protected $signature = 'plugins:sync';

    protected $description = 'Import every new entry from plugins.json across all categories';

    public function handle(): int
    {
        SyncPluginsJsonJob::dispatch();

        $this->info('Queued plugins.json sync.');

        return self::SUCCESS;
    }
}

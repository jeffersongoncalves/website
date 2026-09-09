<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\WarmOgImageJob;
use App\Models\Project;
use Illuminate\Console\Command;

class WarmOgImageCache extends Command
{
    protected $signature = 'projects:warm-og-image-cache {--slug= : Warm only a specific project slug}';

    protected $description = 'Dispatch jobs to pre-fetch and cache each project\'s social card';

    public function handle(): int
    {
        $query = Project::query()->published()
            ->where(function ($q): void {
                $q->whereNotNull('github_url')->orWhereNotNull('social_image');
            });

        if ($slug = $this->option('slug')) {
            $query->where('slug', $slug);
        }

        $projects = $query->get();

        foreach ($projects as $project) {
            WarmOgImageJob::dispatch($project);
        }

        $this->info("Dispatched {$projects->count()} social card warm jobs.");

        return self::SUCCESS;
    }
}

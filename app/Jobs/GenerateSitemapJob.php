<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateSitemapJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * Dispatch-time lock (held until the job starts) so a bulk import that
     * saves hundreds of rows collapses into a single trailing sitemap rebuild
     * instead of one per row — mirrors RefreshProjectStatsJob.
     */
    public int $uniqueFor = 120;

    public function uniqueId(): string
    {
        return 'sitemap:generate';
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('sitemap:generate'))->dontRelease()->expireAfter(120)];
    }

    public function handle(): void
    {
        Artisan::call('sitemap:generate');
    }

    public function failed(?Throwable $e): void
    {
        Log::error('GenerateSitemapJob failed', [
            'error' => $e?->getMessage(),
        ]);
    }
}

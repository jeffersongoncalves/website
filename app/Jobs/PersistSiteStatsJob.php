<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\GithubRateLimitException;
use App\Support\SiteStats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class PersistSiteStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 0 = unlimited attempts; retryUntil() bounds retries by time so repeated
    // rate-limit releases don't trip MaxAttemptsExceededException.
    public int $tries = 0;

    public int $backoff = 30;

    public function __construct()
    {
        $this->onQueue('github');
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(2);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            // This job makes GitHub API calls (followers/sponsors/heatmap) — it
            // must share the same limiter as every other GitHub job, or it runs
            // over budget and persists zeros.
            (new WithoutOverlapping('site-stats:persist'))->dontRelease()->expireAfter(180),
            new RateLimited('github-api'),
        ];
    }

    public function handle(): void
    {
        try {
            SiteStats::persist();
        } catch (GithubRateLimitException $e) {
            // GitHub call failed mid-compute — release instead of writing zeros
            // over the cached followers/sponsors/contribution values.
            $this->release($e->retryAfter);
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('PersistSiteStatsJob failed', [
            'error' => $e?->getMessage(),
        ]);
    }
}

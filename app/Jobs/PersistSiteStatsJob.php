<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\GithubQuota;
use App\Support\SiteStats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use JeffersonGoncalves\GitHubClient\Exceptions\GitHubRateLimitException;
use Throwable;

final class PersistSiteStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 0 = unlimited attempts; retryUntil() bounds retries by time so repeated
    // rate-limit releases don't trip MaxAttemptsExceededException.
    public int $tries = 0;

    public int $backoff = 30;

    // fetchGithubUser() only — fetchSponsorCount() goes through /graphql,
    // which doesn't count against this quota. Worst-case, não medido via
    // GET /rate_limit.
    public const COST = 1;

    public function __construct(public int $staggerSeconds = 0)
    {
        $this->onQueue('github')->onConnection('redis-github');
    }

    public static function make(): static
    {
        $delay = GithubQuota::reserve(self::COST);

        return (new self($delay))->delay($delay);
    }

    public static function enqueue(): void
    {
        dispatch(self::make());
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addSeconds($this->staggerSeconds)->addHour();
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
        } catch (GitHubRateLimitException $e) {
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

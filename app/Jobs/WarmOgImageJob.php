<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Project;
use App\Support\GithubQuota;
use App\Support\OgImageCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pre-fetches and caches a project's social card so the first real visitor
 * (or the first link-preview crawler) never hits the cold GitHub-opengraph
 * fetch — OgImageCache::warm() does the actual work, this job just calls it
 * ahead of time. Mirrors WarmReadmeCacheJob's split for the same reason.
 */
final class WarmOgImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 0 = unlimited attempts. RateLimited below releases the job (consuming
    // an attempt) whenever the shared GitHub budget is exhausted; a finite
    // tries count would let a few throttle releases fail the job outright
    // before it ever got a real turn. retryUntil() bounds it by time instead.
    public int $tries = 0;

    // One opengraph.githubassets.com fetch — CDN, not REST.
    public const COST = 1;

    public function __construct(public Project $project, public int $staggerSeconds = 0)
    {
        $this->onQueue('github')->onConnection('redis-github');
    }

    public static function make(Project $project): static
    {
        // 1000/h keeps headroom under the github-opengraph limiter's 1200/h
        // (20/min) — lowered from the shared github-cdn bucket after ~50% of
        // opengraph.githubassets.com fetches came back 429 in production at
        // 120/min (2026-09-11). raw.githubusercontent.com (README image
        // warming, WarmReadmeCacheJob) never hit that ceiling, so it stays
        // on github-cdn unchanged.
        $delay = GithubQuota::reserve(self::COST, 'github-opengraph', 1000);

        return (new self($project, $delay))->delay($delay);
    }

    public static function enqueue(Project $project): void
    {
        dispatch(self::make($project));
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
            (new WithoutOverlapping("og-image-warm:{$this->project->getKey()}"))->dontRelease(),
            new RateLimited('github-opengraph'),
        ];
    }

    public function handle(): void
    {
        try {
            OgImageCache::warm($this->project);
        } catch (Throwable $e) {
            Log::warning('WarmOgImageJob failed', [
                'project' => $this->project->slug,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('WarmOgImageJob permanently failed', [
            'project' => $this->project->slug,
            'error' => $e?->getMessage(),
        ]);
    }
}

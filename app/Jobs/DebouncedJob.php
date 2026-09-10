<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared ceremony for a job that debounces many triggers (a bulk import, a
 * webhook fired twice) into a single trailing rebuild: ShouldBeUniqueUntilProcessing
 * holds a dispatch-time lock (keyed by uniqueId()) from the moment it is
 * queued until it *starts* processing, so every dispatch landing inside the
 * window collapses into the single already-queued job — the trailing edge
 * still fires, since a dispatch arriving after the job begins processing
 * starts a fresh window. WithoutOverlapping additionally guards against two
 * runs executing concurrently, and expireAfter() bounds both locks in case a
 * hard-killed worker (timeout/OOM/SIGKILL) would otherwise hold them forever.
 *
 * Subclasses implement uniqueId() and handle(); override $expireAfter when
 * the default 120s doesn't fit (e.g. a slower rebuild needs more headroom).
 */
abstract class DebouncedJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * Max seconds the dispatch-time unique lock is held before it is
     * auto-released, in case the job is lost before it starts processing.
     */
    public int $uniqueFor = 120;

    protected int $expireAfter = 120;

    abstract public function uniqueId(): string;

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->dontRelease()->expireAfter($this->expireAfter)];
    }

    public function failed(?Throwable $e): void
    {
        Log::error(static::class.' failed', [
            'error' => $e?->getMessage(),
        ]);
    }
}

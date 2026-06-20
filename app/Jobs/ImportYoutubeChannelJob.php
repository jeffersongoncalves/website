<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Seed a single YouTube channel as a published Project — the YouTube
 * counterpart to ImportWebsiteJob. Same rationale: a raw DB insert keeps the
 * `youtube-<handle>` slug convention that the model's HasSlug would overwrite,
 * and there's no GitHub/Packagist enrichment to run. Idempotent on slug.
 */
class ImportYoutubeChannelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public function __construct(
        public string $handle,
        public string $name,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $slug = 'youtube-'.Str::slug($this->handle);

        if (DB::table('projects')->where('slug', $slug)->exists()) {
            return;
        }

        $title = json_encode(
            ['en' => $this->name, 'pt' => $this->name, 'es' => $this->name],
            JSON_UNESCAPED_UNICODE,
        );

        $now = Carbon::now();

        DB::table('projects')->insert([
            'slug' => $slug,
            'name' => $this->name,
            'category' => 'youtube_channel',
            'title' => $title,
            'stars' => 0,
            'downloads' => 0,
            'user_contributions' => 0,
            'license' => 'MIT',
            // Encode the handle so non-ASCII handles (e.g. @MateusGuimarães)
            // produce a valid URL; a no-op for plain ASCII handles.
            'docs_url' => 'https://www.youtube.com/@'.rawurlencode($this->handle),
            'status' => 'published',
            'featured' => false,
            'is_maintainer' => false,
            'is_daily_driver' => false,
            'is_paid' => false,
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Raw insert bypasses ProjectObserver — the batch dispatches a single
        // delayed RefreshProjectStatsJob to recompute derived stats once.
    }

    public function failed(?Throwable $e): void
    {
        Log::error('ImportYoutubeChannelJob failed', [
            'handle' => $this->handle,
            'name' => $this->name,
            'error' => $e?->getMessage(),
        ]);
    }
}

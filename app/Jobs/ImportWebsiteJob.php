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
 * Seed a single external website as a published Project — the website
 * counterpart to ImportGithubRepoJob, used by catalogue imports for entries
 * with no GitHub repo (hosted tools, link directories, etc).
 *
 * Deliberately a raw DB insert, not a model create: website rows use the
 * `site-<slug>` convention, but the model's HasSlug builds slugs from the repo
 * name and would overwrite it on create. The insert keeps the canonical slug
 * and skips the (irrelevant) GitHub/Packagist enrichment. Idempotent on slug.
 */
class ImportWebsiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public function __construct(
        public string $url,
        public string $name,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $slug = 'site-'.Str::slug($this->name);

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
            'category' => 'website',
            'title' => $title,
            'stars' => 0,
            'downloads' => 0,
            'user_contributions' => 0,
            'license' => 'MIT',
            'docs_url' => $this->url,
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
        Log::error('ImportWebsiteJob failed', [
            'url' => $this->url,
            'name' => $this->name,
            'error' => $e?->getMessage(),
        ]);
    }
}

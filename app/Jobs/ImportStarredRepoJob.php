<?php

namespace App\Jobs;

use App\Exceptions\GithubRateLimitException;
use App\Models\Project;
use App\Support\GithubReadme;
use App\Support\ProjectAttributes;
use App\Support\ProjectImporter;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Enrich and persist a single starred repo as a published Project.
 *
 * Dedup key is the canonical GitHub owner/repo URL. A repo that already exists
 * (a curated row, possibly one the account also starred) is never duplicated
 * and never touched beyond back-filling its starred_at. Brand-new rows are
 * published straight away.
 */
class ImportStarredRepoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $backoff = 30;

    // 0 = unlimited attempts; retryUntil() bounds the retries by time. Avoids
    // MaxAttemptsExceededException when GitHub rate-limit releases pile up.
    public int $tries = 0;

    public function __construct(public string $htmlUrl, public string $starredAt)
    {
        $this->onQueue('github');
    }

    /**
     * Time-based retries so a GitHub rate limit (the RateLimited middleware
     * releases the job) doesn't exhaust a fixed attempt budget — see
     * SyncProjectMetricsJob::retryUntil for the rationale.
     */
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
            (new WithoutOverlapping('import-star:'.$this->canonicalUrl()))->dontRelease(),
            new RateLimited('github-api'),
        ];
    }

    public function handle(): void
    {
        $canonical = $this->canonicalUrl();
        // Keep the star timestamp in UTC end-to-end. The sync cursor reads this
        // column back as UTC too (SyncStarredReposJob), so storage and compare
        // stay consistent regardless of app.timezone — converting to local here
        // would shift the stored wall-clock and let the cursor skip stars inside
        // the timezone-offset window.
        $starredAt = CarbonImmutable::parse($this->starredAt)->utc();

        // Dedup by owner/repo: never duplicate, never downgrade a published row.
        $existing = Project::query()->where('github_url', $canonical)->first();

        if ($existing !== null) {
            if ($existing->starred_at === null) {
                $existing->forceFill(['starred_at' => $starredAt])->save();
            }

            return;
        }

        try {
            $result = ProjectImporter::fromGithub($canonical);
        } catch (GithubRateLimitException $e) {
            $this->release($e->retryAfter);

            return;
        }

        $fields = $result['fields'] ?? null;

        if (! is_array($fields)) {
            $error = $result['error'] ?? 'unknown';

            // Transient failure (network/timeout/unknown): RELEASE to retry
            // within retryUntil instead of dropping the star. The sync cursor is
            // MAX(starred_at); if we silently returned, a newer star importing
            // successfully would advance the cursor past this one and it would
            // never be re-enumerated — permanently lost. Permanent failures
            // (repo deleted/renamed → repo_not_found, or invalid_url) can't be
            // retried, so those still drop.
            if ($error === 'fetch_failed' || $error === 'unknown') {
                Log::warning('ImportStarredRepoJob: transient importer failure, retrying', [
                    'url' => $canonical,
                    'error' => $error,
                ]);

                $this->release($this->backoff);

                return;
            }

            Log::warning('ImportStarredRepoJob: importer failed permanently', [
                'url' => $canonical,
                'error' => $error,
            ]);

            return;
        }

        $attributes = ProjectAttributes::normalize($fields);
        unset($attributes['slug']); // HasSlug regenerates from vendor-repo.

        if (! empty($attributes['name']) && is_string($attributes['name'])) {
            $attributes['name'] = ProjectAttributes::prettifyName($attributes['name']);
        }

        $attributes['github_url'] = $canonical;
        $attributes['status'] = 'published';
        $attributes['is_maintainer'] = false;
        $attributes['is_daily_driver'] = false;

        try {
            // starred_at is guarded (set only by this importer) — forceFill it
            // alongside the mass-assignable attributes.
            $project = (new Project)->fill($attributes);
            $project->forceFill(['starred_at' => $starredAt]);
            $project->save();
        } catch (UniqueConstraintViolationException) {
            // Raced another import for the same repo/slug — already persisted.
        }
    }

    private function canonicalUrl(): string
    {
        $repo = GithubReadme::repoFromUrl($this->htmlUrl);

        return $repo !== null ? 'https://github.com/'.$repo : $this->htmlUrl;
    }

    public function failed(?\Throwable $e): void
    {
        Log::error('ImportStarredRepoJob failed', [
            'html_url' => $this->htmlUrl,
            'error' => $e?->getMessage(),
        ]);
    }
}

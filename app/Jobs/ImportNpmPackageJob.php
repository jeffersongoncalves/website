<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Project;
use App\Support\GithubQuota;
use App\Support\ProjectAttributes;
use App\Support\ProjectImporter;
use App\Support\ProjectMatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use JeffersonGoncalves\GitHubClient\Exceptions\GitHubRateLimitException;
use Throwable;

/**
 * Import a single npm package as a published Project — the npm counterpart to
 * ImportGithubRepoJob. fromNpm reads the registry and recovers the GitHub repo,
 * so it shares the rate-limited `github` queue. Idempotent: dedups on npm_url
 * first, then on the resolved github_url (cross-source), upserting missing
 * fields rather than duplicating.
 */
final class ImportNpmPackageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $backoff = 30;

    public int $tries = 0;

    // Typical cost is 0 — fetchNpmRegistry hits the npm registry, not GitHub.
    // Worst-case is 2 REST calls (fetchDefaultBranchForSlug + subdirectoryHasReadme),
    // only when the registry's `repository` field points at a subdirectory.
    // Worst-case, não medido via GET /rate_limit.
    public const COST = 2;

    public function __construct(
        public string $package,
        public string $fallbackCategory = 'tool',
        public int $staggerSeconds = 0,
    ) {
        $this->onQueue('github')->onConnection('redis-github');
    }

    public static function make(string $package, string $fallbackCategory = 'tool'): static
    {
        $delay = GithubQuota::reserve(self::COST);

        return (new self($package, $fallbackCategory, $delay))->delay($delay);
    }

    public static function enqueue(string $package, string $fallbackCategory = 'tool'): void
    {
        dispatch(self::make($package, $fallbackCategory));
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
            (new WithoutOverlapping('import-npm:'.$this->package))->dontRelease(),
            new RateLimited('github-api'),
        ];
    }

    public function handle(): void
    {
        $npmUrl = 'https://www.npmjs.com/package/'.$this->package;

        // Cheap pre-check on the exact npm_url before the registry call.
        if (Project::query()->where('npm_url', $npmUrl)->exists()) {
            return;
        }

        try {
            $result = ProjectImporter::fromNpm($npmUrl);
        } catch (GitHubRateLimitException $e) {
            $this->release($e->retryAfter);

            return;
        }

        $fields = $result['fields'] ?? null;

        if (! is_array($fields)) {
            $fields = [
                'name' => ProjectAttributes::prettifyName($this->package),
                'category' => $this->fallbackCategory,
                'package_type' => 'npm',
                'npm_url' => $npmUrl,
                'license' => 'MIT',
            ];
        }

        $attributes = ProjectAttributes::normalize($fields);
        unset($attributes['slug']);
        if (! empty($attributes['name']) && is_string($attributes['name'])) {
            $attributes['name'] = ProjectAttributes::prettifyName($attributes['name']);
        }

        // Second canonical pass — picks up a row whose resolved github_url
        // already exists (cross-source dedup).
        $existing = ProjectMatcher::findExisting('npm', $attributes);
        if ($existing !== null) {
            ProjectMatcher::fillMissing($existing, $attributes);
            $existing->save();

            return;
        }

        $attributes['status'] = 'published';
        $attributes['is_daily_driver'] = false;

        try {
            Project::create($attributes);
        } catch (UniqueConstraintViolationException) {
            // Row already exists under a different lookup key.
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('ImportNpmPackageJob failed', [
            'package' => $this->package,
            'error' => $e?->getMessage(),
        ]);
    }
}

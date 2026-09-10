<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Project;
use App\Support\GithubQuota;
use App\Support\GithubReadme;
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
 * Import a single public GitHub repo as a published Project — the unit of work
 * behind bulk catalogue imports (awesome-list seeds, etc).
 *
 * Fan out one of these per repo instead of looping hundreds inline in a single
 * job: each is short, retries independently, and shares the `github` queue's
 * RateLimited middleware so GitHub's limit is respected. Idempotent — a repo
 * already cadastrado is upserted (missing fields only), never duplicated — so a
 * re-dispatch of the whole batch is cheap.
 *
 * The star-feed importer (ImportStarredRepoJob) stays separate: it stamps
 * starred_at and dedups differently. This job is for curated/catalogue repos.
 */
final class ImportGithubRepoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $backoff = 30;

    // 0 = unlimited attempts; retryUntil() bounds retries by time so a GitHub
    // rate-limit release (RateLimited middleware) doesn't exhaust a fixed budget.
    public int $tries = 0;

    // fetchRepo() + fetchBranches() — both REST, always called when the repo
    // is new (fetchManifest/fileExists hit raw.githubusercontent, a separate
    // github-cdn budget). Worst-case, não medido via GET /rate_limit.
    public const COST = 2;

    public function __construct(
        public string $githubUrl,
        public string $fallbackCategory = 'awesome_list',
        public bool $isMaintainer = false,
        public int $staggerSeconds = 0,
    ) {
        $this->onQueue('github');
    }

    public static function make(string $githubUrl, string $fallbackCategory = 'awesome_list', bool $isMaintainer = false): static
    {
        $delay = GithubQuota::reserve(self::COST);

        return (new self($githubUrl, $fallbackCategory, $isMaintainer, $delay))->delay($delay);
    }

    public static function enqueue(string $githubUrl, string $fallbackCategory = 'awesome_list', bool $isMaintainer = false): void
    {
        dispatch(static::make($githubUrl, $fallbackCategory, $isMaintainer));
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
            (new WithoutOverlapping('import-repo:'.$this->canonicalSlug()))->dontRelease(),
            new RateLimited('github-api'),
        ];
    }

    public function handle(): void
    {
        // Cheap canonical pre-check — skip the GitHub API call entirely when a
        // row with the same owner/repo slug is already cadastrado (any URL
        // casing or `/tree/branch/dir` variant).
        if (ProjectMatcher::findByGithubUrl($this->githubUrl) !== null) {
            return;
        }

        try {
            $result = ProjectImporter::fromGithub($this->githubUrl);
        } catch (GitHubRateLimitException $e) {
            $this->release($e->retryAfter);

            return;
        }

        $fields = $result['fields'] ?? null;

        if (! is_array($fields)) {
            $slug = GithubReadme::repoFromUrl($this->githubUrl);
            $name = $slug !== null ? explode('/', $slug, 2)[1] : $this->githubUrl;
            $fields = [
                'github_url' => $this->githubUrl,
                'name' => ProjectAttributes::prettifyName($name),
                'category' => $this->fallbackCategory,
                'package_type' => 'none',
                'license' => 'MIT',
            ];
        }

        $attributes = ProjectAttributes::normalize($fields);
        unset($attributes['slug']); // HasSlug regenerates from vendor-repo.
        if (! empty($attributes['name']) && is_string($attributes['name'])) {
            $attributes['name'] = ProjectAttributes::prettifyName($attributes['name']);
        }

        // Second canonical pass — the importer may have rewritten github_url
        // (npm-flow appending `/tree/<branch>/<dir>`); the pre-check misses those.
        $existing = ProjectMatcher::findExisting('github', $attributes);
        if ($existing !== null) {
            ProjectMatcher::fillMissing($existing, $attributes);

            // fillMissing() only ever fills an empty field, so it can't fix an
            // already-persisted `false` — this is the one field where a
            // plugins.json reclassification (moved into filament.collaborator)
            // must win over whatever the row currently has. Never the other
            // direction: dropping OUT of collaborator doesn't demote a flag
            // that may have been set for an unrelated reason (e.g. a manual
            // admin toggle).
            if ($this->isMaintainer && ! $existing->is_maintainer) {
                $existing->is_maintainer = true;
            }

            $existing->save();

            return;
        }

        $attributes['status'] = 'published';
        $attributes['is_daily_driver'] = false;
        $attributes['is_maintainer'] = $this->isMaintainer;

        try {
            Project::create($attributes);
        } catch (UniqueConstraintViolationException) {
            // Raced another import for the same repo/slug — already persisted.
        }
    }

    private function canonicalSlug(): string
    {
        return GithubReadme::repoFromUrl($this->githubUrl) ?? $this->githubUrl;
    }

    public function failed(?Throwable $e): void
    {
        Log::error('ImportGithubRepoJob failed', [
            'github_url' => $this->githubUrl,
            'error' => $e?->getMessage(),
        ]);
    }
}

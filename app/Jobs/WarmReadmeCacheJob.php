<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ProjectCategory;
use App\Models\Project;
use App\Support\GithubReadme;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use JeffersonGoncalves\NpmReadme\NpmReadme;
use Throwable;

/**
 * Pre-fetches and caches a project's README(s) so the first real visitor
 * after a sync never pays the cold GitHub-fetch + render + sanitize cost —
 * GithubReadme::fetchHtml()/NpmReadme::fetchHtml() do the actual caching,
 * this job just calls them ahead of time. Dispatched by SyncProjectMetricsJob
 * after each project's metrics sync, so the ref/version list it warms is
 * always the just-synced one.
 *
 * A Filament plugin with tracked versions gets every version's ref warmed
 * (not just the default) — a visitor can land on any ?v= via a shared link
 * or the version-switcher chip, and each ref is cached separately.
 */
class WarmReadmeCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 0 = unlimited attempts. RateLimited below releases the job (consuming
    // an attempt) whenever the shared GitHub budget is exhausted; a finite
    // tries count would let a few throttle releases fail the job outright
    // before it ever got a real turn. retryUntil() bounds it by time instead.
    public int $tries = 0;

    public function __construct(public Project $project)
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
            (new WithoutOverlapping("readme-warm:{$this->project->getKey()}"))->dontRelease(),
            new RateLimited('github-api'),
        ];
    }

    public function handle(): void
    {
        try {
            if ($this->project->github_url) {
                foreach ($this->refsToWarm() as $ref) {
                    GithubReadme::fetchHtml($this->project->github_url, $ref);
                }
            } elseif ($this->project->npm_url) {
                NpmReadme::fetchHtml($this->project->npm_url);
            }
        } catch (Throwable $e) {
            Log::warning('WarmReadmeCacheJob failed', [
                'project' => $this->project->slug,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Every ref ProjectShowPage can render for this project: each tracked
     * Filament version's branch (deduped — several versions can share one
     * branch), or just the project's own readme_branch/default branch.
     *
     * @return list<string|null>
     */
    private function refsToWarm(): array
    {
        $isFilamentPlugin = $this->project->category === ProjectCategory::FilamentPlugin;
        $versions = $isFilamentPlugin && is_array($this->project->versions) ? $this->project->versions : [];

        if ($versions === []) {
            return [$this->project->readme_branch ?: null];
        }

        $overrides = is_array($this->project->branch_overrides) ? $this->project->branch_overrides : [];

        $refs = [];

        foreach ($versions as $version) {
            $autoBranch = GithubReadme::branchForFilamentVersion($version, $versions);

            if ($autoBranch === null) {
                continue;
            }

            $override = isset($overrides[$autoBranch]) ? trim((string) $overrides[$autoBranch]) : '';
            $refs[] = $override !== '' ? $override : $autoBranch;
        }

        return array_values(array_unique($refs));
    }

    public function failed(?Throwable $e): void
    {
        Log::error('WarmReadmeCacheJob permanently failed', [
            'project' => $this->project->slug,
            'error' => $e?->getMessage(),
        ]);
    }
}

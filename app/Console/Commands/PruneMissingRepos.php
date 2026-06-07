<?php

namespace App\Console\Commands;

use App\Enums\ProjectCategory;
use App\Exceptions\GithubRateLimitException;
use App\Models\Project;
use App\Support\GithubClient;
use App\Support\GithubReadme;
use Illuminate\Console\Command;

/**
 * Remove projects whose GitHub repo no longer exists. Targets the orphan
 * profile these always share — a github_url but no `repo`, no Packagist/npm
 * link, and not a paid/private package (livewire/flux-pro legitimately 404s) —
 * then confirms each is a real 404 before removing.
 *
 *   - the profile filter runs in SQL, so paid/published packages are never
 *     even checked;
 *   - only a definitive 404 is removed — a transient/5xx/rate-limit is skipped;
 *   - Project has no SoftDeletes, so removal is permanent: dry-run by default,
 *     --delete to remove (prompts when interactive, -n consents for cron).
 */
class PruneMissingRepos extends Command
{
    protected $signature = 'projects:prune-missing-repos
        {--delete : Remove the matched projects (default: dry-run report)}
        {--slug= : Only check this single project slug}';

    protected $description = 'Remove orphan projects whose GitHub repo no longer exists';

    public function handle(): int
    {
        $query = Project::query()
            ->whereNotNull('github_url')
            ->where('github_url', '!=', '')
            ->where('is_paid', false)   // paid packages are private → expected 404
            ->whereNull('packagist_url') // still on Packagist → alive, repo renamed
            ->whereNull('npm_url')       // still on npm → alive, repo renamed
            ->whereNull('repo')          // a real import always backfills `repo`
            // these categories legitimately have a null repo (not GitHub repos)
            ->whereNotIn('category', [
                ProjectCategory::Website->value,
                ProjectCategory::YoutubeChannel->value,
                ProjectCategory::Article->value,
            ]);

        if ($slug = $this->option('slug')) {
            $query->where('slug', $slug);
        }

        /** @var list<array{0:int,1:string,2:string,3:string}> $gone */
        $gone = [];
        $scanned = 0;
        $skipped = 0;
        $rateLimited = false;

        $query->orderBy('id')->chunkById(100, function ($projects) use (&$gone, &$scanned, &$skipped, &$rateLimited): bool {
            foreach ($projects as $project) {
                $repoSlug = GithubReadme::repoFromUrl($project->github_url);

                if ($repoSlug === null) {
                    continue;
                }

                try {
                    $status = GithubClient::repoStatus($repoSlug);
                } catch (GithubRateLimitException $e) {
                    $rateLimited = true;

                    return false;
                }

                $scanned++;

                if ($status === GithubClient::REPO_GONE) {
                    $gone[] = [$project->id, $project->name, $project->slug, (string) $project->github_url];
                } elseif ($status === GithubClient::REPO_UNKNOWN) {
                    $skipped++; // transient/5xx — never prune on doubt
                }
            }

            return true;
        });

        $this->newLine();
        $this->info("Scanned {$scanned} orphan-profile project(s).");

        if ($skipped > 0) {
            $this->comment("{$skipped} could not be verified (transient/rate limit) — skipped.");
        }

        if ($rateLimited) {
            $this->warn('Stopped early on a GitHub rate limit — re-run later to finish.');
        }

        if ($gone === []) {
            $this->info('No missing repos found.');

            return self::SUCCESS;
        }

        $this->error(sprintf('%d project(s) point at a 404 repo:', count($gone)));
        $this->table(['ID', 'Name', 'Slug', 'GitHub URL'], $gone);

        if (! $this->option('delete')) {
            $this->comment('Dry run — re-run with --delete to remove these (permanent).');

            return self::SUCCESS;
        }

        if ($this->input->isInteractive()
            && ! $this->confirm(sprintf('Permanently delete these %d project(s)?', count($gone)), false)) {
            $this->info('Aborted — nothing removed.');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($gone as [$id]) {
            $project = Project::query()->find($id);

            if ($project !== null) {
                $project->delete(); // via model so ProjectObserver busts caches/sitemap
                $deleted++;
            }
        }

        $this->info("Removed {$deleted} project(s).");

        return self::SUCCESS;
    }
}

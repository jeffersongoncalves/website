<?php

namespace App\Console\Commands;

use App\Exceptions\GithubRateLimitException;
use App\Models\Project;
use App\Support\GithubClient;
use App\Support\GithubReadme;
use Illuminate\Console\Command;

/**
 * Check every GitHub-backed project against the GitHub API and report (or
 * remove) the ones whose repo no longer resolves — deleted, renamed, or turned
 * private (e.g. https://github.com/beyondcode/laravel-face-auth → 404).
 *
 * Conservative by design:
 *   - default run is a DRY-RUN report; nothing is touched without --delete;
 *   - only a definitive 404 ("gone") is a removal candidate. A transient
 *     failure / 5xx / rate limit is "unknown" and is NEVER removed;
 *   - Project has no SoftDeletes, so removal is permanent — --delete prompts
 *     for confirmation unless run with -n / --no-interaction.
 */
class PruneMissingRepos extends Command
{
    protected $signature = 'projects:prune-missing-repos
        {--delete : Remove the projects whose repo returned 404 (default: dry-run report)}
        {--slug= : Only check this single project slug}';

    protected $description = 'Find (and optionally remove) projects whose GitHub repo no longer exists';

    public function handle(): int
    {
        $query = Project::query()
            ->whereNotNull('github_url')
            ->where('github_url', '!=', '');

        if ($slug = $this->option('slug')) {
            $query->where('slug', $slug);
        }

        /** @var list<array{0:int,1:string,2:string,3:string}> $gone */
        $gone = [];
        /** @var list<array{0:int,1:string,2:string,3:string}> $unknown */
        $unknown = [];
        /** @var list<array{0:int,1:string,2:string,3:string}> $paidGone */
        $paidGone = [];
        $checked = 0;
        $rateLimited = false;

        $query->orderBy('id')->chunkById(100, function ($projects) use (&$gone, &$unknown, &$paidGone, &$checked, &$rateLimited): bool {
            foreach ($projects as $project) {
                $repoSlug = GithubReadme::repoFromUrl($project->github_url);

                if ($repoSlug === null) {
                    continue; // not a parseable owner/repo URL — out of scope
                }

                try {
                    $status = GithubClient::repoStatus($repoSlug);
                } catch (GithubRateLimitException $e) {
                    $rateLimited = true;

                    return false; // stop the chunk loop; report what we have
                }

                $checked++;
                $row = [$project->id, $project->name, $project->slug, (string) $project->github_url];

                if ($status === GithubClient::REPO_GONE) {
                    // Paid packages (e.g. livewire/flux-pro) are private, so a
                    // 404 on the public API is expected — never prune those.
                    if ($project->is_paid) {
                        $paidGone[] = $row;
                    } else {
                        $gone[] = $row;
                    }
                } elseif ($status === GithubClient::REPO_UNKNOWN) {
                    $unknown[] = $row;
                }
            }

            return true;
        });

        $this->newLine();
        $this->info("Checked {$checked} GitHub project(s).");

        if ($unknown !== []) {
            $this->newLine();
            $this->comment(sprintf('%d could not be verified (transient/5xx/rate limit) — left untouched:', count($unknown)));
            $this->table(['ID', 'Name', 'Slug', 'GitHub URL'], $unknown);
        }

        if ($paidGone !== []) {
            $this->newLine();
            $this->comment(sprintf('%d paid/private project(s) returned 404 (expected — kept):', count($paidGone)));
            $this->table(['ID', 'Name', 'Slug', 'GitHub URL'], $paidGone);
        }

        $this->newLine();

        if ($gone === []) {
            $this->info('No missing repos found.');

            if ($rateLimited) {
                $this->warn('Stopped early on a GitHub rate limit — re-run later to finish.');
            }

            return self::SUCCESS;
        }

        $this->error(sprintf('%d project(s) point at a repo that returned 404 (deleted/renamed/private):', count($gone)));
        $this->table(['ID', 'Name', 'Slug', 'GitHub URL'], $gone);

        if ($rateLimited) {
            $this->warn('Stopped early on a GitHub rate limit — re-run later to check the rest.');
        }

        if (! $this->option('delete')) {
            $this->newLine();
            $this->comment('Dry run — nothing removed. Re-run with --delete to remove these (permanent).');

            return self::SUCCESS;
        }

        // Prompt only when interactive; -n / --no-interaction (cron) takes
        // --delete as the explicit consent.
        if ($this->input->isInteractive()
            && ! $this->confirm(sprintf('Permanently delete these %d project(s)?', count($gone)), false)) {
            $this->info('Aborted — nothing removed.');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($gone as [$id]) {
            // Delete via the model so ProjectObserver busts the caches/sitemap.
            $project = Project::query()->find($id);

            if ($project !== null) {
                $project->delete();
                $deleted++;
            }
        }

        $this->info("Removed {$deleted} project(s).");

        return self::SUCCESS;
    }
}

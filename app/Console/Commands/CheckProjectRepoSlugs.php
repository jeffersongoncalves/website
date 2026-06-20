<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Project;
use App\Support\GithubReadme;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Audit (read-only) every GitHub-backed project for a slug/repo that doesn't
 * match what the importer would derive from its github_url.
 *
 * The importer canonicalises a GitHub project as:
 *   - slug  = Str::slug("{owner}-{repo}")   e.g. mbostock/d3 -> "mbostock-d3"
 *   - repo  = "{repo}"  (bare name)          e.g. mbostock/d3 -> "d3"
 *
 * Rows seeded outside that path (npm/link-importer seeds, hand edits) can end
 * up with the bare repo name as the slug ("d3") — losing the owner prefix and
 * risking collisions between two owners' same-named repos. This command finds
 * them; it never writes (use --fix wiring later if you want to repair).
 */
class CheckProjectRepoSlugs extends Command
{
    protected $signature = 'projects:check-repo-slugs
        {--all : Include lower-confidence mismatches (e.g. monorepo subdir slugs), not just the missing-owner case}';

    protected $description = 'Report GitHub projects whose slug/repo do not match what github_url implies';

    public function handle(): int
    {
        $bareSlug = [];      // high confidence: slug is just the repo name, owner dropped
        $otherSlug = [];     // lower confidence: slug differs but isn't the bare-name case
        $repoMismatch = [];  // repo column != bare repo name the url implies
        $unparseable = [];   // has a github_url we can't read owner/repo out of

        Project::query()
            ->whereNotNull('github_url')
            ->where('github_url', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($projects) use (&$bareSlug, &$otherSlug, &$repoMismatch, &$unparseable): void {
                foreach ($projects as $project) {
                    $ownerRepo = GithubReadme::repoFromUrl($project->github_url);

                    if ($ownerRepo === null) {
                        $unparseable[] = [$project->id, $project->slug, (string) $project->github_url];

                        continue;
                    }

                    [$owner, $repoName] = explode('/', $ownerRepo, 2);
                    $expectedSlug = Str::slug($owner.'-'.$repoName);
                    $bareName = Str::slug($repoName);

                    if ($project->slug !== $expectedSlug) {
                        $row = [$project->id, $project->name, $project->slug, $expectedSlug, $project->github_url];

                        // Bare-name slug == the importer's slug for the repo name
                        // alone, i.e. the owner prefix was dropped. The headline bug.
                        if ($project->slug === $bareName) {
                            $bareSlug[] = $row;
                        } else {
                            $otherSlug[] = $row;
                        }
                    }

                    if ((string) $project->repo !== $repoName) {
                        $repoMismatch[] = [$project->id, $project->name, (string) $project->repo, $repoName, $project->github_url];
                    }
                }
            });

        $this->reportBareSlugs($bareSlug);

        if ($this->option('all')) {
            $this->reportOtherSlugs($otherSlug);
            $this->reportRepoMismatch($repoMismatch);
            $this->reportUnparseable($unparseable);
        }

        $this->newLine();
        $this->line(sprintf(
            'Summary: <fg=red>%d</> missing-owner slug(s), %d other slug mismatch(es), %d repo mismatch(es), %d unparseable url(s).',
            count($bareSlug),
            count($otherSlug),
            count($repoMismatch),
            count($unparseable),
        ));

        if (! $this->option('all') && (count($otherSlug) + count($repoMismatch) + count($unparseable)) > 0) {
            $this->comment('Re-run with --all to list the lower-confidence mismatches.');
        }

        return $bareSlug === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<array{0:int,1:string,2:string,3:string,4:string}>  $rows
     */
    private function reportBareSlugs(array $rows): void
    {
        $this->newLine();
        $this->info('Missing-owner slugs (slug is the bare repo name):');

        if ($rows === []) {
            $this->line('  none');

            return;
        }

        $this->table(['ID', 'Name', 'Slug (now)', 'Slug (expected)', 'GitHub URL'], $rows);
    }

    /**
     * @param  list<array{0:int,1:string,2:string,3:string,4:string}>  $rows
     */
    private function reportOtherSlugs(array $rows): void
    {
        $this->newLine();
        $this->info('Other slug mismatches (may be intentional — monorepo subdir, manual rename):');

        if ($rows === []) {
            $this->line('  none');

            return;
        }

        $this->table(['ID', 'Name', 'Slug (now)', 'Slug (expected)', 'GitHub URL'], $rows);
    }

    /**
     * @param  list<array{0:int,1:string,2:string,3:string,4:string}>  $rows
     */
    private function reportRepoMismatch(array $rows): void
    {
        $this->newLine();
        $this->info('Repo column != bare repo name from github_url:');

        if ($rows === []) {
            $this->line('  none');

            return;
        }

        $this->table(['ID', 'Name', 'Repo (now)', 'Repo (expected)', 'GitHub URL'], $rows);
    }

    /**
     * @param  list<array{0:int,1:string,2:string}>  $rows
     */
    private function reportUnparseable(array $rows): void
    {
        $this->newLine();
        $this->info('Unparseable github_url (no owner/repo):');

        if ($rows === []) {
            $this->line('  none');

            return;
        }

        $this->table(['ID', 'Slug', 'GitHub URL'], $rows);
    }
}

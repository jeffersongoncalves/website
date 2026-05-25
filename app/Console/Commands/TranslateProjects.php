<?php

namespace App\Console\Commands;

use App\Enums\ProjectCategory;
use App\Jobs\TranslateProjectTitleJob;
use App\Models\Project;
use Illuminate\Console\Command;

class TranslateProjects extends Command
{
    protected $signature = 'projects:translate
        {--slug= : Queue only a specific project slug}
        {--force : Re-translate even when pt/es already differ from the English source}';

    protected $description = 'Dispatch TranslateProjectTitleJob for every project except Website / YoutubeChannel rows (proper nouns).';

    public function handle(): int
    {
        $query = Project::query()
            ->whereNotIn('category', [
                ProjectCategory::Website->value,
                ProjectCategory::YoutubeChannel->value,
            ])
            ->whereNotNull('title');

        if ($slug = $this->option('slug')) {
            $query->where('slug', $slug);
        }

        $force = (bool) $this->option('force');
        $dispatched = 0;
        $skipped = 0;

        $query->orderBy('id')->chunkById(100, function ($projects) use (&$dispatched, &$skipped, $force): void {
            foreach ($projects as $project) {
                $english = $project->getTranslation('title', 'en', false);

                if (! is_string($english) || trim($english) === '') {
                    $skipped++;

                    continue;
                }

                // Without --force, skip projects whose pt AND es already
                // hold a string that diverges from the English source —
                // those have either been manually localised or already
                // translated by a previous run.
                if (! $force) {
                    $pt = $project->getTranslation('title', 'pt', false);
                    $es = $project->getTranslation('title', 'es', false);
                    $ptDone = is_string($pt) && trim($pt) !== '' && $pt !== $english;
                    $esDone = is_string($es) && trim($es) !== '' && $es !== $english;

                    if ($ptDone && $esDone) {
                        $skipped++;

                        continue;
                    }
                }

                TranslateProjectTitleJob::dispatch($project);
                $dispatched++;
            }
        });

        $this->info("Dispatched {$dispatched} translate jobs to the `translations` queue (skipped {$skipped}).");

        return self::SUCCESS;
    }
}

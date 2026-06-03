<?php

namespace App\Jobs;

use App\Models\Project;
use App\Support\ProjectImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Re-read a published article's real publish date from its source page and
 * correct `published_at`. Articles imported before the importer learned to read
 * `article:published_time` carry the import date instead of the post's actual
 * date; this backfills the accurate one for the schema.org datePublished + the
 * site's article list. Idempotent: re-fetches the live date and only writes when
 * it differs by more than a day, so re-running is a no-op.
 */
class BackfillArticleDateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $projectId)
    {
        $this->onQueue('default');
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('backfill-article-date:'.$this->projectId))->dontRelease()];
    }

    public function handle(): void
    {
        $project = Project::query()->find($this->projectId);

        if ($project === null || empty($project->docs_url)) {
            return;
        }

        $result = ProjectImporter::fromArticle($project->docs_url);
        $published = $result['fields']['published_at'] ?? null;

        if (! is_string($published)) {
            // Source ships no parseable date — leave the existing value alone.
            return;
        }

        $real = Carbon::parse($published);

        // Skip the no-op write when the stored date is already correct (within a
        // day, to ignore timezone/rounding noise) so a re-run touches nothing.
        if ($project->published_at !== null && $project->published_at->diffInDays($real, true) < 1) {
            return;
        }

        // forceFill + saveQuietly: this is a metric-style correction, not an
        // editorial change — no need to wake the observer (cache flush / sitemap
        // / stats) for a date tweak; the batch's own RefreshProjectStatsJob runs.
        $project->forceFill(['published_at' => $real])->saveQuietly();
    }
}

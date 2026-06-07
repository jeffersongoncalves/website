<?php

use App\Support\GithubReadme;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The original github_owner backfill used a looser regex (github.com/<owner>)
 * than ProjectObserver::saving, which derives the owner via
 * GithubReadme::repoFromUrl() — requiring a full <owner>/<repo> path and
 * returning null otherwise. A bare owner/profile URL was therefore stamped by
 * the backfill but reset to null on the next save, diverging the two paths.
 *
 * Re-normalise every row through the SAME extractor the observer uses so the
 * stored owner is consistent regardless of when a row was last saved.
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('projects')
            ->whereNotNull('github_url')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $slug = GithubReadme::repoFromUrl((string) $row->github_url);
                    $owner = $slug !== null ? strtolower(explode('/', $slug, 2)[0]) : null;

                    if ($owner !== $row->github_owner) {
                        DB::table('projects')->where('id', $row->id)->update(['github_owner' => $owner]);
                    }
                }
            });
    }

    public function down(): void
    {
        // No-op: this only corrects data, the column/index are unchanged.
    }
};

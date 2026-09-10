<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GitHub's numeric repo id is the only stable identity across a rename or
 * transfer (the API 301s owner/repo → the new slug but keeps the same id).
 * `github_url` alone can't dedupe those cases — two rows can point at the
 * same repo under different slugs, or the same slug in different casing.
 * Backfilled by App\Jobs\BackfillGithubRepoIdJob (projects:backfill-github-repo-id),
 * then App\Console\Commands\MergeDuplicateProjects collapses any row that
 * shares an id.
 *
 * Deliberately NOT unique yet — production has existing duplicates, and a
 * unique constraint would make the backfill itself fail on the second half
 * of every duplicate pair (the first row to claim an id would block the
 * other from ever being written), so the merge command could never find
 * them via `whereNotNull('github_repo_id')`. Add the unique constraint in a
 * separate follow-up migration once `projects:merge-duplicate-repos` reports
 * zero duplicate groups.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('github_repo_id')->nullable()->index()->after('github_url');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('github_repo_id');
        });
    }
};

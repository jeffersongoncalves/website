<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalise the GitHub owner login out of `github_url` into its own indexed
 * column. The "authored / owned repos" facets matched with
 * `whereRaw('lower(github_url) like ?')`, which can never use an index — the
 * lower() wrapper alone defeats it. An exact-match on a lowercased,
 * single-column index serves the same query from the index instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'github_owner')) {
                $table->string('github_owner')->nullable()->after('github_url');
                $table->index('github_owner');
            }
        });

        // Backfill existing rows: extract `<owner>` from
        // https://github.com/<owner>/<repo> and store it lowercased.
        DB::table('projects')
            ->whereNotNull('github_url')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    if (preg_match('~github\.com/([^/?#]+)~i', (string) $row->github_url, $m) === 1) {
                        DB::table('projects')
                            ->where('id', $row->id)
                            ->update(['github_owner' => strtolower($m[1])]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['github_owner']);
            $table->dropColumn('github_owner');
        });
    }
};

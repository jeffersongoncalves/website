<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index the columns the public catalogue sorts and filters on. Every
 * /projects page sorts by stars (default), downloads, or name under a
 * `status = published` filter, and the role/language facets filter on
 * is_daily_driver / is_maintainer / language — none of which were indexed,
 * forcing a full scan per request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Composite (status, <sort>) so the published filter + the default
            // ORDER BY are served by one index.
            $table->index(['status', 'stars']);
            $table->index(['status', 'downloads']);
            $table->index(['status', 'name']);
            // Role facet filters. `language` is already indexed by the
            // add_language_to_projects_table migration.
            $table->index('is_daily_driver');
            $table->index('is_maintainer');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['status', 'stars']);
            $table->dropIndex(['status', 'downloads']);
            $table->dropIndex(['status', 'name']);
            $table->dropIndex(['is_daily_driver']);
            $table->dropIndex(['is_maintainer']);
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The dashboard's global widgets (HasGlobalStatsPayload) fan out to every
 * short url site-wide: `WHERE short_url_id IN (...) AND <date column>
 * BETWEEN ? AND ?`. The existing short_url_id-led indexes serve the
 * per-link case well but make the DB range-scan per id for the global
 * (unfiltered id list) case instead of range-scanning once on the date
 * column. See jeffersongoncalves/laravel-short-url#18 (fixed upstream in
 * laravel-short-url#19, 4.4.3) — these tables are app-owned migrations, so
 * the matching index has to land here too.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('short-url.table_prefix', 'short_url_');

        Schema::table($prefix.'visits', function (Blueprint $table) {
            $table->index(['visited_at', 'short_url_id']);
        });

        Schema::table($prefix.'daily_stats', function (Blueprint $table) {
            $table->index(['date', 'short_url_id']);
        });
    }

    public function down(): void
    {
        $prefix = config('short-url.table_prefix', 'short_url_');

        Schema::table($prefix.'visits', function (Blueprint $table) {
            $table->dropIndex(['visited_at', 'short_url_id']);
        });

        Schema::table($prefix.'daily_stats', function (Blueprint $table) {
            $table->dropIndex(['date', 'short_url_id']);
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('short-url.table_prefix', 'short_url_');

        // The global (site-wide) dashboard aggregates across every short_url_id at
        // once — an id-led index serves that IN (...) list poorly. A date-led index
        // matches the query's real selectivity (a narrow date range). See issue #18.
        //
        // This app already created the same two indexes via the now-superseded
        // 2026_09_11_120000_add_date_led_indexes_to_short_url_tables workaround
        // (laravel-short-url#18/#19), so guard both — this migration is only a
        // real no-op here, not on a fresh install that never had that workaround.
        Schema::table($prefix.'visits', function (Blueprint $table) use ($prefix) {
            if (! Schema::hasIndex($prefix.'visits', ['visited_at', 'short_url_id'])) {
                $table->index(['visited_at', 'short_url_id']);
            }
        });

        Schema::table($prefix.'daily_stats', function (Blueprint $table) use ($prefix) {
            if (! Schema::hasIndex($prefix.'daily_stats', ['date', 'short_url_id'])) {
                $table->index(['date', 'short_url_id']);
            }
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

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `published_at`/`unavailable_at`/`starred_at`/`last_synced_at` were `timestamp
 * without time zone` — Postgres does zero timezone conversion on that type, it
 * just compares the naive numeral. With APP_TIMEZONE=America/Sao_Paulo, any
 * process whose PHP env briefly disagreed on the app timezone (e.g. a queue
 * worker not yet restarted after a config change) could write a numeral in a
 * different zone than the one used to build the `now()` comparison in
 * Project::scopePublished(), silently hiding a just-published row for hours.
 *
 * `timestamptz` stores an absolute instant instead, so the ambiguity moves to
 * a single, fixed point: the session's `TIME ZONE` setting used to interpret
 * naive input (pinned via config/database.php `timezone` in the same commit).
 * Existing naive values were always written under APP_TIMEZONE=America/Sao_Paulo,
 * so `AT TIME ZONE 'America/Sao_Paulo'` reinterprets them correctly on convert.
 */
return new class extends Migration
{
    private const COLUMNS = ['published_at', 'last_synced_at', 'starred_at', 'unavailable_at'];

    public function up(): void
    {
        // SQLite (the test suite's connection) has no real column typing —
        // every value already round-trips through it untyped, so there is
        // nothing to convert there.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::COLUMNS as $column) {
            if (! Schema::hasColumn('projects', $column)) {
                continue;
            }

            DB::statement(
                "alter table projects alter column {$column} type timestamptz using {$column} at time zone 'America/Sao_Paulo'"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::COLUMNS as $column) {
            if (! Schema::hasColumn('projects', $column)) {
                continue;
            }

            DB::statement(
                "alter table projects alter column {$column} type timestamp using {$column} at time zone 'America/Sao_Paulo'"
            );
        }
    }
};

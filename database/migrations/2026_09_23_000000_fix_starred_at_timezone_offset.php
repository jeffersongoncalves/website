<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ImportStarredRepoJob used to hand Eloquent a UTC Carbon, which is written as a
 * naive wall-clock. Postgres interprets naive input in the session TIME ZONE
 * (APP_TIMEZONE), so every starred_at landed offset hours late — and the
 * timestamptz conversion migration reinterpreted the older naive values the
 * same way. Re-read each value's local wall-clock as the UTC it really was.
 *
 * SQLite/MySQL never ran the timestamptz conversion and only serve tests, so
 * there is nothing to correct there.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->shift("(starred_at at time zone ?) at time zone 'UTC'");
    }

    public function down(): void
    {
        $this->shift("(starred_at at time zone 'UTC') at time zone ?");
    }

    private function shift(string $expression): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::update(
            "update projects set starred_at = {$expression} where starred_at is not null",
            [config('app.timezone')]
        );
    }
};

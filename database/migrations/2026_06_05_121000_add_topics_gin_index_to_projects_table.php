<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The #topic catalogue filter runs `whereJsonContains('topics', <slug>)` on
 * every /projects request. On Postgres that compiles to the `@>` containment
 * operator, which a GIN index on a jsonb column serves directly instead of
 * scanning the table. Convert topics json -> jsonb and add the GIN index.
 *
 * Postgres-only: SQLite (local/tests) has no jsonb/GIN and keeps using
 * json_each for whereJsonContains, so this is a no-op there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE projects ALTER COLUMN topics TYPE jsonb USING topics::jsonb');
        DB::statement('CREATE INDEX IF NOT EXISTS projects_topics_gin ON projects USING gin (topics)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS projects_topics_gin');
        DB::statement('ALTER TABLE projects ALTER COLUMN topics TYPE json USING topics::json');
    }
};

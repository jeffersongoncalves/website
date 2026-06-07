<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a Filament plugin tracks per-version branches (v3/v4/v5 → 1.x/2.x/…)
 * whose READMEs are fetched and shown via the version switcher. When false the
 * plugin renders a single README from its default branch. Paid plugins never
 * have it (they're private — no public branches).
 *
 * Schema only: existing plugins are backfilled by the
 * `backfill_has_branches_for_filament_plugins` one-time operation, which runs
 * after this migration on deploy. Data backfills belong in operations, not
 * migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('has_branches')->default(false)->after('readme_branch');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('has_branches');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a Filament plugin tracks per-version branches (v3/v4/v5 → 1.x/2.x/…)
 * whose READMEs are fetched and shown via the version switcher. When false the
 * plugin renders a single README from its default branch. Paid plugins never
 * have it (they're private — no public branches). Backfilled true for existing
 * plugins that already carry a non-empty `versions` list so their version
 * chips keep showing in the admin form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('has_branches')->default(false)->after('readme_branch');
        });

        DB::table('projects')
            ->where('category', 'filament_plugin')
            ->where('is_paid', false)
            ->whereNotNull('versions')
            ->where('versions', '!=', '[]')
            ->update(['has_branches' => true]);
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('has_branches');
        });
    }
};

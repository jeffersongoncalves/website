<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Re-adds `starred_at` — the GitHub stars-import feature was reverted back in
 * (see git history: "Revert 'feat(projects): remove the GitHub stars import
 * feature'"), but the earlier drop migration (2026_06_09_000000) was never
 * paired with a re-add, so the column stayed missing on any DB that had
 * already run it. Guarded by hasColumn so a DB where the drop never ran (or
 * this migration runs twice) is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('projects', 'starred_at')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->timestamp('starred_at')->nullable()->index()->after('last_synced_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('projects', 'starred_at')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex(['starred_at']);
            $table->dropColumn('starred_at');
        });
    }
};

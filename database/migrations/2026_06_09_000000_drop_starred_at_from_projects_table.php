<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops `starred_at` — the GitHub stars-import feature was removed. Guarded by
 * hasColumn so a fresh install (where the add-migration no longer exists) is a
 * no-op while a deployed DB that still carries the column gets it dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('projects', 'starred_at')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex(['starred_at']);
            $table->dropColumn('starred_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('projects', 'starred_at')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->timestamp('starred_at')->nullable()->index()->after('last_synced_at');
        });
    }
};

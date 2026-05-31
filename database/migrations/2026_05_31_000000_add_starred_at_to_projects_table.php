<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `starred_at` records when the GitHub account starred this repo. It doubles
 * as the incremental sync cursor: `MAX(starred_at)` is the high-water mark the
 * star-sync job pages back to (curated rows keep it null and are ignored).
 * Indexed because every sync derives the cursor from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('starred_at')->nullable()->index()->after('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['starred_at']);
            $table->dropColumn('starred_at');
        });
    }
};

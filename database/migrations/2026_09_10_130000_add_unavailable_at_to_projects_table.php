<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stamped by BackfillGithubRepoIdJob when GitHub confirms a 404 (repo
 * deleted/renamed-away-with-no-redirect/made private) — never on a transient
 * failure. Project::scopePublished() excludes it, so the row drops out of
 * every public listing/show query without ever being deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('unavailable_at')->nullable()->after('github_repo_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('unavailable_at');
        });
    }
};

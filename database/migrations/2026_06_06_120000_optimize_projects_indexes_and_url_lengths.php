<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * URL columns default to VARCHAR(255), but project URLs (deep GitHub
     * subtree links, scraped social_image URLs) routinely exceed that — on
     * PostgreSQL an over-length value errors the insert rather than truncating.
     */
    private const URL_COLUMNS = [
        'github_url',
        'packagist_url',
        'npm_url',
        'docker_url',
        'docs_url',
        'demo_url',
        'social_image',
    ];

    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            // published_at drives the published() scope and the article
            // list/feed ordering — index it to avoid full scans.
            $table->index('published_at');
        });

        Schema::table('projects', function (Blueprint $table): void {
            foreach (self::URL_COLUMNS as $column) {
                if (Schema::hasColumn('projects', $column)) {
                    $table->string($column, 500)->nullable()->change();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex(['published_at']);
        });

        // URL columns are left widened — narrowing back to 255 would risk
        // truncating rows that legitimately use the extra length.
    }
};

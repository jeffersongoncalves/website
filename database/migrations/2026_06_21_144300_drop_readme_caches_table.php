<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The app-side `readme_caches` table is superseded by the
 * `github_readme_cache` table owned by jeffersongoncalves/laravel-github-readme.
 * Both hold only regenerable README cache rows, so dropping the old one is safe
 * — the package re-fetches and re-populates on demand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('readme_caches');
    }

    public function down(): void
    {
        Schema::create('readme_caches', function (Blueprint $table) {
            $table->id();
            $table->string('repo');
            $table->string('ref')->default('default');
            $table->string('etag')->nullable();
            $table->string('default_branch')->nullable();
            $table->string('html_path')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['repo', 'ref']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the og:image captured from imported article/website pages so the
 * project's social card uses the source's own image instead of the one generic
 * site banner. GitHub repos keep using opengraph.githubassets.com at runtime.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('social_image', 500)->nullable()->after('docs_url');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('social_image');
        });
    }
};

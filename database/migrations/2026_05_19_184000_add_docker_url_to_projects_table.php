<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Docker Hub repository URL (https://hub.docker.com/r/{owner}/{repo}).
            // Drives the pull_count download metric for docker-category
            // projects. Sits next to the other distribution URLs.
            $table->string('docker_url')->nullable()->after('npm_url');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('docker_url');
        });
    }
};

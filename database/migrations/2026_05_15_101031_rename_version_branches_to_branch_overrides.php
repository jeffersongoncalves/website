<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('version_branches');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->json('branch_overrides')->nullable()->after('versions');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('branch_overrides');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->json('version_branches')->nullable()->after('versions');
        });
    }
};

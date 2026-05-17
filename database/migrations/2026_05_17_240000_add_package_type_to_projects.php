<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records whether a project ships as a Composer package, an npm package,
     * a JetBrains plugin, or nothing publishable. Drives which manifest
     * (`composer.json` vs `package.json`) the metrics sync should read to
     * verify the published name, and which downloads API to call.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('package_type', 16)->nullable()->after('category');
        });

        DB::table('projects')->whereNotNull('packagist_url')->update(['package_type' => 'composer']);
        DB::table('projects')->whereNull('package_type')->whereNotNull('npm_url')->update(['package_type' => 'npm']);
        DB::table('projects')->whereNull('package_type')->whereNotNull('docs_url')->where('docs_url', 'like', '%plugins.jetbrains.com%')->update(['package_type' => 'jetbrains']);
        DB::table('projects')->whereNull('package_type')->update(['package_type' => 'none']);
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('package_type');
        });
    }
};

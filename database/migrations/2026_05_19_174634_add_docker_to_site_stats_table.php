<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_stats', function (Blueprint $table) {
            // Drops in after `tool` to keep the column order matching the
            // ProjectCategory enum priority list. Default 0 so existing
            // rows don't need a backfill pass.
            $table->unsignedInteger('docker')->default(0)->after('tool');
        });
    }

    public function down(): void
    {
        Schema::table('site_stats', function (Blueprint $table) {
            $table->dropColumn('docker');
        });
    }
};

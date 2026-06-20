<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_stats', function (Blueprint $table) {
            $table->unsignedBigInteger('downloads_packagist')->default(0)->after('downloads');
            $table->unsignedBigInteger('downloads_npm')->default(0)->after('downloads_packagist');
            $table->unsignedBigInteger('downloads_jetbrains')->default(0)->after('downloads_npm');
        });
    }

    public function down(): void
    {
        Schema::table('site_stats', function (Blueprint $table) {
            $table->dropColumn(['downloads_packagist', 'downloads_npm', 'downloads_jetbrains']);
        });
    }
};

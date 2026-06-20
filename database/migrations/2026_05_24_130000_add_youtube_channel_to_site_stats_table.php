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
            $table->unsignedInteger('youtube_channel')->default(0)->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('site_stats', function (Blueprint $table) {
            $table->dropColumn('youtube_channel');
        });
    }
};

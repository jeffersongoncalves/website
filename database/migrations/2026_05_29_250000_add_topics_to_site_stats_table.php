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
            if (! Schema::hasColumn('site_stats', 'topics')) {
                $table->json('topics')->nullable()->after('languages');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_stats', function (Blueprint $table) {
            $table->dropColumn('topics');
        });
    }
};

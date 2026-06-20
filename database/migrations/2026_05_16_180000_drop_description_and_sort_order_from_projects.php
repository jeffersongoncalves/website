<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
            $table->dropColumn(['description', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('description')->nullable()->after('title');
            $table->unsignedInteger('sort_order')->default(0)->after('is_maintainer');
            $table->index('sort_order');
        });
    }
};

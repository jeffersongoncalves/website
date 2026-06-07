<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PostgreSQL does not auto-index FK columns (unlike MySQL). project_id is
     * queried by the slugAliases() relation (auto eager-loaded) and the
     * cascade-on-delete path, so add the supporting index explicitly.
     */
    public function up(): void
    {
        Schema::table('project_slug_aliases', function (Blueprint $table): void {
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('project_slug_aliases', function (Blueprint $table): void {
            $table->dropIndex(['project_id']);
        });
    }
};

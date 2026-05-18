<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_stats', function (Blueprint $table) {
            $table->unsignedInteger('livewire')->default(0)->after('laravel');
            $table->unsignedInteger('cakephp')->default(0)->after('livewire');
            $table->unsignedInteger('laravel_zero')->default(0)->after('cakephp');
            $table->unsignedInteger('ide_plugin')->default(0)->after('laravel_zero');
            $table->unsignedInteger('framework')->default(0)->after('ide_plugin');
            $table->unsignedInteger('saas')->default(0)->after('framework');
        });
    }

    public function down(): void
    {
        Schema::table('site_stats', function (Blueprint $table) {
            $table->dropColumn([
                'livewire',
                'cakephp',
                'laravel_zero',
                'ide_plugin',
                'framework',
                'saas',
            ]);
        });
    }
};

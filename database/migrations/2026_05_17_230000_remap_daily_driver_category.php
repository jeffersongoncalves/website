<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "daily_driver" is no longer a category — it's only a boolean flag
     * (`is_daily_driver`). Remap any existing rows whose category still
     * points at the removed enum case to the closest real category, so the
     * cast on `App\Models\Project::$category` keeps working.
     */
    public function up(): void
    {
        $map = [
            'https://github.com/alpinejs/alpine' => 'framework',
            'https://github.com/tailwindlabs/tailwindcss' => 'framework',
            'https://github.com/wire-elements/modal' => 'livewire_package',
            'https://github.com/achyutn/filament-log-viewer' => 'filament_plugin',
            'https://github.com/dutchcodingcompany/filament-developer-logins' => 'filament_plugin',
            'https://github.com/laravel/horizon' => 'laravel_package',
            'https://github.com/ralphjsmit/laravel-seo' => 'laravel_package',
            'https://github.com/spatie/laravel-sitemap' => 'laravel_package',
            'https://github.com/spatie/laravel-sluggable' => 'laravel_package',
            'https://github.com/spatie/laravel-translatable' => 'laravel_package',
            'https://github.com/barryvdh/laravel-debugbar' => 'laravel_package',
            'https://github.com/barryvdh/laravel-ide-helper' => 'laravel_package',
            'https://github.com/fakerphp/faker' => 'tool',
            'https://github.com/larastan/larastan' => 'tool',
            'https://github.com/pestphp/pest' => 'tool',
            'https://github.com/pestphp/pest-plugin-laravel' => 'laravel_package',
        ];

        foreach ($map as $githubUrl => $category) {
            DB::table('projects')
                ->where('github_url', $githubUrl)
                ->where('category', 'daily_driver')
                ->update(['category' => $category]);
        }

        DB::table('projects')
            ->where('category', 'daily_driver')
            ->update(['category' => 'tool']);
    }

    public function down(): void {}
};

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property int $repos
 * @property int $catalogue
 * @property array<int, array{language: string, total: int}>|null $languages
 * @property array<int, array{topic: string, total: int}>|null $topics
 * @property int $filament
 * @property int $laravel
 * @property int $livewire
 * @property int $cakephp
 * @property int $laravel_zero
 * @property int $ide_plugin
 * @property int $framework
 * @property int $starter
 * @property int $saas
 * @property int $tool
 * @property int $docker
 * @property int $database
 * @property int $website
 * @property int $youtube_channel
 * @property int $php_package
 * @property int $javascript_package
 * @property int $css_framework
 * @property int $application
 * @property int $learning_resource
 * @property int $awesome_list
 * @property int $mobile_library
 * @property int $maintained
 * @property int $daily_drivers
 * @property int $stars
 * @property int $downloads
 * @property int $downloads_packagist
 * @property int $downloads_npm
 * @property int $downloads_jetbrains
 * @property int $downloads_docker
 * @property int $followers
 * @property int $public_sponsors
 * @property array{cells: list<int>, total: int}|null $contributions
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Eloquent
 */
class SiteStat extends Model
{
    /**
     * Cache key for the assembled stats array (see SiteStats::all()). The row is
     * a singleton written only via Eloquent (SiteStats::persist /
     * refreshProjectDerived), so a saved/deleted hook is enough to keep the
     * cache honest — no writer has to remember to flush.
     */
    public const CACHE_KEY = 'site_stats';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    protected $fillable = [
        'repos',
        'catalogue',
        'languages',
        'topics',
        'filament',
        'laravel',
        'livewire',
        'cakephp',
        'laravel_zero',
        'ide_plugin',
        'framework',
        'starter',
        'saas',
        'tool',
        'docker',
        'database',
        'website',
        'youtube_channel',
        'php_package',
        'javascript_package',
        'css_framework',
        'application',
        'learning_resource',
        'awesome_list',
        'mobile_library',
        'maintained',
        'daily_drivers',
        'stars',
        'downloads',
        'downloads_packagist',
        'downloads_npm',
        'downloads_jetbrains',
        'downloads_docker',
        'followers',
        'public_sponsors',
        'contributions',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'repos' => 'integer',
            'catalogue' => 'integer',
            'languages' => 'array',
            'topics' => 'array',
            'filament' => 'integer',
            'laravel' => 'integer',
            'livewire' => 'integer',
            'cakephp' => 'integer',
            'laravel_zero' => 'integer',
            'ide_plugin' => 'integer',
            'framework' => 'integer',
            'starter' => 'integer',
            'saas' => 'integer',
            'tool' => 'integer',
            'docker' => 'integer',
            'database' => 'integer',
            'website' => 'integer',
            'youtube_channel' => 'integer',
            'php_package' => 'integer',
            'javascript_package' => 'integer',
            'css_framework' => 'integer',
            'application' => 'integer',
            'learning_resource' => 'integer',
            'awesome_list' => 'integer',
            'mobile_library' => 'integer',
            'maintained' => 'integer',
            'daily_drivers' => 'integer',
            'stars' => 'integer',
            'downloads' => 'integer',
            'downloads_packagist' => 'integer',
            'downloads_npm' => 'integer',
            'downloads_jetbrains' => 'integer',
            'downloads_docker' => 'integer',
            'followers' => 'integer',
            'public_sponsors' => 'integer',
            'contributions' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property int $repos
 * @property int $filament
 * @property int $laravel
 * @property int $starter
 * @property int $tool
 * @property int $stars
 * @property int $downloads
 * @property int $followers
 * @property int $public_sponsors
 * @property array{cells: list<int>, total: int}|null $contributions
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $maintained
 * @property int $daily_drivers
 * @property int $downloads_packagist
 * @property int $downloads_npm
 * @property int $downloads_jetbrains
 * @property int $livewire
 * @property int $cakephp
 * @property int $laravel_zero
 * @property int $ide_plugin
 * @property int $framework
 * @property int $saas
 * @property int $docker
 * @property int $database
 * @property int $downloads_docker
 * @property int $website
 * @property int $youtube_channel
 * @property int $php_package
 * @property int $javascript_package
 * @property int $css_framework
 * @property int $application
 * @property int $learning_resource
 * @property int $awesome_list
 * @property int $mobile_library
 * @property int $catalogue
 * @property list<array{language: string, total: int}>|null $languages
 * @property list<array{topic: string, total: int}>|null $topics
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereApplication($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereAwesomeList($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereCakephp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereCatalogue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereContributions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereCssFramework($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereDailyDrivers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereDatabase($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereDocker($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereDownloads($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereDownloadsDocker($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereDownloadsJetbrains($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereDownloadsNpm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereDownloadsPackagist($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereFilament($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereFollowers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereFramework($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereIdePlugin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereJavascriptPackage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereLanguages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereLaravel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereLaravelZero($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereLearningResource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereLivewire($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereMaintained($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereMobileLibrary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat wherePhpPackage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat wherePublicSponsors($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereRepos($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereSaas($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereStars($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereStarter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereSyncedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereTool($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereTopics($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereWebsite($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SiteStat whereYoutubeChannel($value)
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

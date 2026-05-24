<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $repos
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
    protected $fillable = [
        'repos',
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

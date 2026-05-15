<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
 *
 * @mixin \Eloquent
 */
class SiteStat extends Model
{
    protected $fillable = [
        'repos',
        'filament',
        'laravel',
        'starter',
        'tool',
        'stars',
        'downloads',
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
            'starter' => 'integer',
            'tool' => 'integer',
            'stars' => 'integer',
            'downloads' => 'integer',
            'followers' => 'integer',
            'public_sponsors' => 'integer',
            'contributions' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}

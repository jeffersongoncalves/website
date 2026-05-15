<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $repo
 * @property string $ref
 * @property string|null $etag
 * @property string|null $default_branch
 * @property string|null $html_path
 * @property Carbon|null $fetched_at
 * @property Carbon|null $checked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Eloquent
 */
class ReadmeCache extends Model
{
    protected $fillable = [
        'repo',
        'ref',
        'etag',
        'default_branch',
        'html_path',
        'fetched_at',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
            'checked_at' => 'datetime',
        ];
    }
}

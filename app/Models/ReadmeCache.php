<?php

declare(strict_types=1);

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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadmeCache newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadmeCache newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadmeCache query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadmeCache whereCheckedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadmeCache whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadmeCache whereDefaultBranch($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadmeCache whereEtag($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadmeCache whereFetchedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadmeCache whereHtmlPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadmeCache whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadmeCache whereRef($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadmeCache whereRepo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadmeCache whereUpdatedAt($value)
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

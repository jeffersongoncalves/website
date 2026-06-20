<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A retired project slug kept alive for redirects. When a project's slug is
 * canonicalised (e.g. "d3" -> "mbostock-d3") the old value is recorded here so
 * the show route can 301 the stale URL to the project's current slug instead
 * of 404-ing every existing bookmark/backlink.
 *
 * @property int $id
 * @property int $project_id
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectSlugAlias newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectSlugAlias newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectSlugAlias query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectSlugAlias whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectSlugAlias whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectSlugAlias whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectSlugAlias whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectSlugAlias whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ProjectSlugAlias extends Model
{
    protected $fillable = ['project_id', 'slug'];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}

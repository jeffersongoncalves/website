<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A retired project slug kept alive for redirects. When a project's slug is
 * canonicalised (e.g. "d3" -> "mbostock-d3") the old value is recorded here so
 * the show route can 301 the stale URL to the project's current slug instead
 * of 404-ing every existing bookmark/backlink.
 *
 * @property int $id
 * @property int $project_id
 * @property string $slug
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

<?php

namespace App\Models;

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Observers\ProjectObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

#[ObservedBy(ProjectObserver::class)]
class Project extends Model
{
    use HasFactory;
    use HasSlug;
    use HasTranslations;

    protected $fillable = [
        'slug',
        'name',
        'repo',
        'category',
        'title',
        'description',
        'content',
        'versions',
        'stack',
        'stars',
        'downloads',
        'downloads_label',
        'license',
        'github_url',
        'packagist_url',
        'docs_url',
        'demo_url',
        'cover_image',
        'status',
        'featured',
        'sort_order',
        'published_at',
        'last_synced_at',
    ];

    public array $translatable = [
        'title',
        'description',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'versions'       => 'array',
            'stack'          => 'array',
            'stars'          => 'integer',
            'downloads'      => 'integer',
            'featured'       => 'boolean',
            'sort_order'     => 'integer',
            'category'       => ProjectCategory::class,
            'status'         => ProjectStatus::class,
            'published_at'   => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ProjectStatus::Published);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured', true);
    }

    public function scopeByCategory(Builder $query, ProjectCategory|string $category): Builder
    {
        $value = $category instanceof ProjectCategory ? $category->value : $category;

        return $query->where('category', $value);
    }

    public function getGithubUrlAttribute(?string $value): ?string
    {
        if ($value) {
            return $value;
        }

        $repo = $this->repo ?: $this->slug;

        return $repo ? "https://github.com/jeffersongoncalves/{$repo}" : null;
    }
}

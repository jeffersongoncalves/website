<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Observers\PostObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

#[ObservedBy(PostObserver::class)]
class Post extends Model
{
    use HasFactory;
    use HasSlug;
    use HasTranslations;

    protected $fillable = [
        'slug',
        'title',
        'excerpt',
        'body',
        'tags',
        'cover_image',
        'reading_time',
        'views',
        'status',
        'published_at',
    ];

    public array $translatable = [
        'title',
        'excerpt',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'tags'         => 'array',
            'reading_time' => 'integer',
            'views'        => 'integer',
            'status'       => PostStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(fn (Post $post) => $post->getTranslation('title', config('app.fallback_locale', 'pt'), false) ?: 'post')
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}

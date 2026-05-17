<?php

namespace App\Models;

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Observers\ProjectObserver;
use App\Support\LocaleSupport;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RalphJSmit\Laravel\SEO\Support\HasSEO;
use RalphJSmit\Laravel\SEO\Support\SEOData;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $repo
 * @property ProjectCategory $category
 * @property array<array-key, mixed>|null $title
 * @property array<array-key, mixed>|null $content
 * @property array<array-key, mixed>|null $versions
 * @property array<array-key, mixed>|null $branch_overrides
 * @property string|null $readme_branch
 * @property array<array-key, mixed>|null $stack
 * @property int $stars
 * @property int $downloads
 * @property string|null $downloads_label
 * @property int $user_contributions
 * @property string $license
 * @property string|null $github_url
 * @property string|null $packagist_url
 * @property string|null $npm_url
 * @property string|null $docs_url
 * @property string|null $demo_url
 * @property string|null $cover_image
 * @property ProjectStatus $status
 * @property bool $featured
 * @property bool $is_maintainer
 * @property bool $is_daily_driver
 * @property Carbon|null $published_at
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read array $translatable_columns_from
 * @property-read mixed $translations
 *
 * @method static Builder<static>|Project authored()
 * @method static Builder<static>|Project byCategory(\App\Enums\ProjectCategory|string $category)
 * @method static Builder<static>|Project featured()
 * @method static Builder<static>|Project maintained()
 * @method static Builder<static>|Project newModelQuery()
 * @method static Builder<static>|Project newQuery()
 * @method static Builder<static>|Project published()
 * @method static Builder<static>|Project query()
 * @method static Builder<static>|Project whereCategory($value)
 * @method static Builder<static>|Project whereContent($value)
 * @method static Builder<static>|Project whereCoverImage($value)
 * @method static Builder<static>|Project whereCreatedAt($value)
 * @method static Builder<static>|Project whereDemoUrl($value)
 * @method static Builder<static>|Project whereDocsUrl($value)
 * @method static Builder<static>|Project whereDownloads($value)
 * @method static Builder<static>|Project whereDownloadsLabel($value)
 * @method static Builder<static>|Project whereFeatured($value)
 * @method static Builder<static>|Project whereGithubUrl($value)
 * @method static Builder<static>|Project whereId($value)
 * @method static Builder<static>|Project whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static Builder<static>|Project whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static Builder<static>|Project whereLastSyncedAt($value)
 * @method static Builder<static>|Project whereLicense($value)
 * @method static Builder<static>|Project whereLocale(string $column, string $locale)
 * @method static Builder<static>|Project whereLocales(string $column, array $locales)
 * @method static Builder<static>|Project whereName($value)
 * @method static Builder<static>|Project wherePackagistUrl($value)
 * @method static Builder<static>|Project wherePublishedAt($value)
 * @method static Builder<static>|Project whereRepo($value)
 * @method static Builder<static>|Project whereSlug($value)
 * @method static Builder<static>|Project whereStack($value)
 * @method static Builder<static>|Project whereStars($value)
 * @method static Builder<static>|Project whereStatus($value)
 * @method static Builder<static>|Project whereTitle($value)
 * @method static Builder<static>|Project whereUpdatedAt($value)
 * @method static Builder<static>|Project whereVersions($value)
 *
 * @mixin \Eloquent
 */
#[ObservedBy(ProjectObserver::class)]
class Project extends Model
{
    use HasFactory;
    use HasSEO;
    use HasSlug;
    use HasTranslations;

    protected $fillable = [
        'slug',
        'name',
        'repo',
        'category',
        'title',
        'content',
        'versions',
        'branch_overrides',
        'readme_branch',
        'stack',
        'stars',
        'downloads',
        'downloads_label',
        'user_contributions',
        'license',
        'github_url',
        'packagist_url',
        'npm_url',
        'docs_url',
        'demo_url',
        'cover_image',
        'status',
        'featured',
        'is_maintainer',
        'is_daily_driver',
        'published_at',
        'last_synced_at',
    ];

    public array $translatable = [
        'title',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'versions' => 'array',
            'branch_overrides' => 'array',
            'stack' => 'array',
            'stars' => 'integer',
            'downloads' => 'integer',
            'user_contributions' => 'integer',
            'featured' => 'boolean',
            'is_maintainer' => 'boolean',
            'is_daily_driver' => 'boolean',
            'category' => ProjectCategory::class,
            'status' => ProjectStatus::class,
            'published_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(fn (Project $m): string => $m->buildVendorRepoSlug())
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function buildVendorRepoSlug(): string
    {
        if ($this->github_url && preg_match('~github\.com/([^/]+)/([^/?#]+)~i', $this->github_url, $m)) {
            return $m[1].'-'.rtrim($m[2], '/');
        }

        return (string) ($this->name ?? '');
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

    public function scopeMaintained(Builder $query): Builder
    {
        return $query->where('is_maintainer', true);
    }

    public function scopeAuthored(Builder $query): Builder
    {
        return $query->where('is_maintainer', false);
    }

    public function getDynamicSEOData(): SEOData
    {
        $locale = LocaleSupport::short();
        $title = $this->getTranslation('title', $locale, false) ?: $this->name;

        return new SEOData(
            title: $title,
            author: 'Jefferson Gonçalves',
            image: $this->cover_image
                ? Storage::url($this->cover_image)
                : null,
            published_time: $this->published_at,
            modified_time: $this->updated_at,
            type: 'article',
        );
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

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PackageType;
use App\Enums\ProjectCategory;
use App\Enums\ProjectLanguage;
use App\Enums\ProjectStatus;
use App\Observers\ProjectObserver;
use App\Support\GithubReadme;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use JeffersonGoncalves\LocaleCookie\LocaleCookie;
use RalphJSmit\Laravel\SEO\Models\SEO;
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
 * @property list<string>|null $versions
 * @property array<array-key, mixed>|null $stack
 * @property int $stars
 * @property int $downloads
 * @property string|null $downloads_label
 * @property string $license
 * @property string|null $github_url
 * @property int|null $github_repo_id
 * @property Carbon|null $unavailable_at
 * @property string|null $packagist_url
 * @property string|null $docs_url
 * @property string|null $demo_url
 * @property ProjectStatus $status
 * @property bool $featured
 * @property Carbon|null $published_at
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $starred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $readme_branch
 * @property array<array-key, mixed>|null $branch_overrides
 * @property bool $is_maintainer
 * @property int $user_contributions
 * @property bool $is_daily_driver
 * @property string|null $npm_url
 * @property bool $is_paid
 * @property PackageType|null $package_type
 * @property string|null $docker_url
 * @property ProjectLanguage|null $language
 * @property array<array-key, mixed>|null $topics
 * @property string|null $social_image
 * @property string|null $github_owner
 * @property bool $has_branches
 * @property-read array<int, string> $translatable_columns_from
 * @property-read SEO $seo
 * @property-read Collection<int, ProjectSlugAlias> $slugAliases
 * @property-read int|null $slug_aliases_count
 * @property-read mixed $translations
 *
 * @method static Builder<static>|Project authored()
 * @method static Builder<static>|Project byCategory(\App\Enums\ProjectCategory|string $category)
 * @method static Builder<static>|Project byLanguage(string $language)
 * @method static Builder<static>|Project featured()
 * @method static Builder<static>|Project maintained()
 * @method static Builder<static>|Project newModelQuery()
 * @method static Builder<static>|Project newQuery()
 * @method static Builder<static>|Project published()
 * @method static Builder<static>|Project query()
 * @method static Builder<static>|Project whereBranchOverrides($value)
 * @method static Builder<static>|Project whereCategory($value)
 * @method static Builder<static>|Project whereContent($value)
 * @method static Builder<static>|Project whereCreatedAt($value)
 * @method static Builder<static>|Project whereDemoUrl($value)
 * @method static Builder<static>|Project whereDockerUrl($value)
 * @method static Builder<static>|Project whereDocsUrl($value)
 * @method static Builder<static>|Project whereDownloads($value)
 * @method static Builder<static>|Project whereDownloadsLabel($value)
 * @method static Builder<static>|Project whereFeatured($value)
 * @method static Builder<static>|Project whereGithubOwner($value)
 * @method static Builder<static>|Project whereGithubUrl($value)
 * @method static Builder<static>|Project whereHasBranches($value)
 * @method static Builder<static>|Project whereId($value)
 * @method static Builder<static>|Project whereIsDailyDriver($value)
 * @method static Builder<static>|Project whereIsMaintainer($value)
 * @method static Builder<static>|Project whereIsPaid($value)
 * @method static Builder<static>|Project whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static Builder<static>|Project whereJsonContainsLocales(string $column, array<int, string> $locales, ?mixed $value, string $operand = '=')
 * @method static Builder<static>|Project whereLanguage($value)
 * @method static Builder<static>|Project whereLastSyncedAt($value)
 * @method static Builder<static>|Project whereLicense($value)
 * @method static Builder<static>|Project whereLocale(string $column, string $locale)
 * @method static Builder<static>|Project whereLocales(string $column, array<int, string> $locales)
 * @method static Builder<static>|Project whereName($value)
 * @method static Builder<static>|Project whereNpmUrl($value)
 * @method static Builder<static>|Project wherePackageType($value)
 * @method static Builder<static>|Project wherePackagistUrl($value)
 * @method static Builder<static>|Project wherePublishedAt($value)
 * @method static Builder<static>|Project whereReadmeBranch($value)
 * @method static Builder<static>|Project whereRepo($value)
 * @method static Builder<static>|Project whereSlug($value)
 * @method static Builder<static>|Project whereSocialImage($value)
 * @method static Builder<static>|Project whereStack($value)
 * @method static Builder<static>|Project whereStars($value)
 * @method static Builder<static>|Project whereStatus($value)
 * @method static Builder<static>|Project whereTitle($value)
 * @method static Builder<static>|Project whereTopics($value)
 * @method static Builder<static>|Project whereUpdatedAt($value)
 * @method static Builder<static>|Project whereUserContributions($value)
 * @method static Builder<static>|Project whereVersions($value)
 *
 * @mixin \Eloquent
 */
#[ObservedBy(ProjectObserver::class)]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use HasSEO;
    use HasSlug;
    use HasTranslations;

    protected $fillable = [
        'slug',
        'name',
        'repo',
        'category',
        'package_type',
        'language',
        'title',
        'content',
        'versions',
        'branch_overrides',
        'readme_branch',
        'has_branches',
        'stack',
        'topics',
        'stars',
        'downloads',
        'downloads_label',
        'user_contributions',
        'license',
        'github_url',
        'github_repo_id',
        'packagist_url',
        'npm_url',
        'docker_url',
        'docs_url',
        'social_image',
        'demo_url',
        'status',
        'featured',
        'is_maintainer',
        'is_daily_driver',
        'is_paid',
        'published_at',
        'last_synced_at',
        // starred_at is intentionally NOT mass-assignable — it is internal,
        // stamped only by ImportStarredRepoJob via forceFill().
    ];

    /** @var list<string> */
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
            'topics' => 'array',
            'stars' => 'integer',
            'downloads' => 'integer',
            'user_contributions' => 'integer',
            'has_branches' => 'boolean',
            'featured' => 'boolean',
            'is_maintainer' => 'boolean',
            'is_daily_driver' => 'boolean',
            'is_paid' => 'boolean',
            'category' => ProjectCategory::class,
            'package_type' => PackageType::class,
            'language' => ProjectLanguage::class,
            'status' => ProjectStatus::class,
            'published_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'starred_at' => 'datetime',
            'unavailable_at' => 'datetime',
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
        $isGithub = $this->github_url
            && preg_match('~github\.com/([^/]+)/([^/?#]+)~i', $this->github_url, $m);
        $isMonorepoSubtree = $isGithub && str_contains((string) $this->github_url, '/tree/');

        // npm packages from monorepo subtrees (e.g. Laravel's precognition
        // family under laravel/framework) would collapse to the same
        // owner-repo slug. For those, prefer the npm package name to keep
        // siblings distinct.
        if ($isMonorepoSubtree
            && $this->package_type === PackageType::Npm && $this->npm_url
            && preg_match('~/package/(.+?)/?$~', $this->npm_url, $npmMatch)
        ) {
            $package = rawurldecode($npmMatch[1]);

            return Str::slug(str_replace(['@', '/'], ['', '-'], $package));
        }

        if ($isGithub) {
            return $m[1].'-'.rtrim($m[2], '/');
        }

        // Standalone npm packages without a github_url fall back to the
        // package name.
        if ($this->package_type === PackageType::Npm && $this->npm_url
            && preg_match('~/package/(.+?)/?$~', $this->npm_url, $npmMatch)
        ) {
            $package = rawurldecode($npmMatch[1]);

            return Str::slug(str_replace(['@', '/'], ['', '-'], $package));
        }

        // Articles carry the `article-` prefix so the URL stays visually
        // distinct from packages/sites — mirrors the importer's slug.
        if ($this->category === ProjectCategory::Article) {
            return 'article-'.Str::slug((string) ($this->name ?? ''));
        }

        return (string) ($this->name ?? '');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The route name for this project's canonical public section: articles →
     * articles.show, external links (sites, channels, learning resources,
     * awesome lists) → links.show, code projects → projects.show. Single source
     * of truth for the card, sitemap and ProjectViewController's 301.
     */
    public function canonicalRouteName(): string
    {
        return match (true) {
            $this->category === ProjectCategory::Article => 'articles.show',
            $this->category->isExternalLink() => 'links.show',
            default => 'projects.show',
        };
    }

    /**
     * The canonical public URL for this project, in its proper section.
     */
    public function publicUrl(): string
    {
        return route($this->canonicalRouteName(), ['slug' => $this->slug]);
    }

    /**
     * The `title` translation for a locale, falling back to English when the
     * requested locale is missing or blank (the MCP tools' locale param has
     * no cookie/session to fall back on the way the site's own middleware
     * does). Returns null only when neither the requested locale nor English
     * has a usable value.
     */
    public function localizedTitle(?string $locale = null): ?string
    {
        $locale ??= 'en';

        $title = $this->getTranslation('title', $locale, false);
        if (is_string($title) && trim($title) !== '') {
            return trim($title);
        }

        if ($locale === 'en') {
            return null;
        }

        $fallback = $this->getTranslation('title', 'en', false);

        return is_string($fallback) && trim($fallback) !== '' ? trim($fallback) : null;
    }

    /**
     * Retired slugs that 301-redirect to this project's current slug.
     *
     * @return HasMany<ProjectSlugAlias, $this>
     */
    public function slugAliases(): HasMany
    {
        return $this->hasMany(ProjectSlugAlias::class);
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ProjectStatus::Published)
            // Hide rows scheduled for a future publish date. Legacy rows with a
            // null published_at stay visible (the observer only stamps it going
            // forward), so null is treated as "already live".
            ->where(function (Builder $q): void {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            // A confirmed 404 on GitHub (BackfillGithubRepoIdJob) — hidden, not
            // deleted, so a manual re-check can clear it later.
            ->whereNull('unavailable_at');
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured', true);
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeByCategory(Builder $query, ProjectCategory|string $category): Builder
    {
        $value = $category instanceof ProjectCategory ? $category->value : $category;

        return $query->where('category', $value);
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeByLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeMaintained(Builder $query): Builder
    {
        return $query->where('is_maintainer', true);
    }

    /**
     * Projects the owner actually authored — the repo lives under his GitHub
     * account (same rule as isCreatedByOwner / the "creator" badge). NOT merely
     * `is_maintainer = false`, which also matched every starred third-party repo
     * and wrongly listed them as authored.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeAuthored(Builder $query): Builder
    {
        $username = config('services.github.username');

        if (! is_string($username) || $username === '') {
            // No owner configured — nothing can be proven authored.
            return $query->whereRaw('1 = 0');
        }

        // Exact-match the denormalised, indexed owner login (kept lowercased by
        // the ProjectObserver). Replaces a `lower(github_url) like` scan that
        // could never use an index.
        return $query->where('github_owner', strtolower($username));
    }

    /**
     * Projects Jefferson actively maintains but doesn't own — `is_maintainer`
     * true on a repo whose `github_owner` isn't his (the plugins.json
     * `filament.collaborator` group, e.g. rmsramos/activitylog,
     * joaopaulolndev/filament-*). Distinct from authored(): a repo can be
     * both (rare — is_maintainer manually set true on his own repo means
     * nothing extra) but the interesting set for a "not mine, but I help
     * maintain it" listing is this one, authored() excluded.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeCollaborated(Builder $query): Builder
    {
        $username = config('services.github.username');
        $normalized = is_string($username) && $username !== '' ? strtolower($username) : null;

        return $query->where('is_maintainer', true)
            ->where(function (Builder $q) use ($normalized): void {
                $q->whereNull('github_owner');

                if ($normalized !== null) {
                    $q->orWhere('github_owner', '!=', $normalized);
                }
            });
    }

    /**
     * Third-party repos imported from the GitHub stars feed (starred_at set).
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeStarred(Builder $query): Builder
    {
        return $query->whereNotNull('starred_at');
    }

    /**
     * Curated/own projects — everything that did NOT come from a star import.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeOwn(Builder $query): Builder
    {
        return $query->whereNull('starred_at');
    }

    /**
     * Whether this repo lives under the site owner's GitHub account — i.e. a
     * package Jefferson created himself, as opposed to one he only maintains or
     * starred. Drives the "creator" badge.
     */
    public function isCreatedByOwner(): bool
    {
        $username = config('services.github.username');

        if (! is_string($username) || $username === '' || empty($this->github_url)) {
            return false;
        }

        $slug = GithubReadme::repoFromUrl($this->github_url);

        return $slug !== null && strcasecmp(explode('/', $slug, 2)[0], $username) === 0;
    }

    public function getDynamicSEOData(): SEOData
    {
        $locale = LocaleCookie::short();
        $description = $this->getTranslation('title', $locale, false) ?: $this->name;

        // Point og:image at our own cached proxy (/og/{slug}.png) rather than
        // GitHub's opengraph endpoint directly — that endpoint 429s crawlers.
        // The proxy resolves the GitHub card or the imported social_image and
        // caches it; with neither, it redirects to the generic banner. Only set
        // it when there's a source so the SEO transformer's banner is used.
        $hasGithub = $this->github_url
            && preg_match('~github\.com/[^/?#]+/[^/?#]+~i', $this->github_url) === 1;

        $image = ($hasGithub || ! empty($this->social_image))
            ? route('og.show', ['slug' => $this->slug])
            : null;

        return new SEOData(
            // Matches components.site.layouts.app's own $title concat (name +
            // site name) — this page supplies a full SEOData object instead of
            // that layout's `title` prop, so it has to replicate the suffix
            // itself or the <title> would be the bare project name.
            title: $this->name.' — '.config('app.name'),
            // Only attribute authorship for repos under the owner's account —
            // the catalogue is mostly third-party, so a blanket author would be
            // false (mirrors the JSON-LD author rule in project-detail).
            description: $description,
            author: $this->isCreatedByOwner() ? 'Jefferson Gonçalves' : null,
            image: $image,
            published_time: $this->published_at,
            modified_time: $this->updated_at,
            type: 'article',
        );
    }
}

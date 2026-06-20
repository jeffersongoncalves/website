<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ProjectCategory;
use App\Models\Project;
use App\Models\ProjectSlugAlias;
use App\Support\SiteStats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Reclassify Website projects whose docs_url path looks like a single blog
 * post / article (e.g. /blog/my-post, /articles/foo, /2024/05/bar) as Article
 * projects, so they move from the /links hub to the /articles section.
 *
 * Conservative on purpose: only a marker segment FOLLOWED by a post slug, or a
 * dated archive path, qualifies — a bare blog root (/, /blog) stays a Website.
 * Idempotent: it only touches category=website rows, so a second run finds
 * nothing left to convert.
 *
 * Shared by the one-time operation (dispatched once) and the
 * `projects:migrate-blog-sites` command (ad-hoc reruns).
 */
class MigrateBlogSitesToArticlesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct()
    {
        $this->onQueue('default');
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('projects:migrate-blog-sites'))->dontRelease()->expireAfter(600)];
    }

    /**
     * @return int the number of websites reclassified as articles
     */
    public function handle(): int
    {
        $converted = 0;

        Project::query()
            ->where('category', ProjectCategory::Website->value)
            ->whereNotNull('docs_url')
            ->orderBy('id')
            ->each(function (Project $project) use (&$converted): void {
                $path = (string) parse_url((string) $project->docs_url, PHP_URL_PATH);

                if (! $this->looksLikeArticle($path)) {
                    return;
                }

                $oldSlug = $project->slug;
                $newSlug = $this->articleSlug($project);

                if ($newSlug !== $oldSlug) {
                    // Keep the retired Website slug alive — old /links/{slug}
                    // and /projects/{slug} links 301 to the new /articles/{slug}.
                    ProjectSlugAlias::query()->firstOrCreate(
                        ['slug' => $oldSlug],
                        ['project_id' => $project->id],
                    );

                    $project->slug = $newSlug;
                }

                $project->category = ProjectCategory::Article;
                $project->save();

                $converted++;
            });

        if ($converted > 0) {
            SiteStats::refreshProjectDerived();
        }

        logger()->info("migrate_blog_sites_to_articles: reclassified {$converted} website(s) as articles");

        return $converted;
    }

    public function failed(?\Throwable $e): void
    {
        logger()->error('MigrateBlogSitesToArticlesJob failed', [
            'error' => $e?->getMessage(),
        ]);
    }

    /**
     * A blog/article marker segment followed by a post slug, or a dated archive
     * path. Bare roots and listing pages stay as websites.
     */
    private function looksLikeArticle(string $path): bool
    {
        if ($path === '' || $path === '/') {
            return false;
        }

        if (preg_match('~/(blog|article|articles|artigo|artigos|post|posts|news|noticia|noticias|story|stories|tutorial|tutorials)/[^/]+~i', $path) === 1) {
            return true;
        }

        return preg_match('~/\d{4}/\d{1,2}/[^/]+~', $path) === 1;
    }

    /**
     * The canonical article slug for a project (mirrors the importer's
     * `article-` prefix), suffixed with the id if another project already holds
     * it.
     */
    private function articleSlug(Project $project): string
    {
        $base = 'article-'.Str::slug((string) $project->name);

        $taken = Project::query()
            ->where('slug', $base)
            ->where('id', '!=', $project->id)
            ->exists();

        return $taken ? $base.'-'.$project->id : $base;
    }
}

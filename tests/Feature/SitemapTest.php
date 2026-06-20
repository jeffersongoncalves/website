<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function sitemapProject(string $name, ProjectCategory $category, string $slug): Project
{
    return Project::query()->create([
        'name' => $name,
        'slug' => $slug,
        'category' => $category,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
    ]);
}

it('writes a single urlset sitemap (not an index) with changefreq + priority on every entry', function () {
    sitemapProject('My Package', ProjectCategory::LaravelPackage, 'pkg');
    sitemapProject('My Article', ProjectCategory::Article, 'article-my-article');
    sitemapProject('A Site', ProjectCategory::Website, 'site-a-site');

    $this->artisan('sitemap:generate')->assertSuccessful();

    $xml = (string) file_get_contents(public_path('sitemap.xml'));

    expect($xml)
        // a single inline urlset, not a sitemapindex pointing at sub-files
        ->toContain('<urlset')
        ->not->toContain('<sitemapindex')
        ->not->toContain('sitemap-pages.xml')
        // pages
        ->toContain('<loc>'.route('home').'</loc>')
        ->toContain('<priority>1.0</priority>')
        ->toContain('<changefreq>daily</changefreq>')
        ->toContain('<changefreq>monthly</changefreq>')
        ->toContain('<lastmod>')
        // projects under their canonical sections, tiered by kind
        ->toContain('/projects/pkg')
        ->toContain('/articles/article-my-article')
        ->toContain('/links/site-a-site')
        ->toContain('<priority>0.7</priority>') // code project
        ->toContain('<priority>0.6</priority>') // article
        ->toContain('<priority>0.5</priority>'); // external link
});

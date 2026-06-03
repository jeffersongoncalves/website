<?php

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

it('writes pages with a changefreq and priority on every entry', function () {
    $this->artisan('sitemap:generate')->assertSuccessful();

    $pages = (string) file_get_contents(public_path('sitemap-pages.xml'));

    expect($pages)
        ->toContain('<loc>'.route('home').'</loc>')
        ->toContain('<priority>1.0</priority>')        // home is top priority
        ->toContain('<changefreq>daily</changefreq>')  // catalogue indexes
        ->toContain('<changefreq>monthly</changefreq>') // evergreen pages
        ->toContain('<lastmod>');
});

it('tiers project priority by kind: code above articles above external links', function () {
    sitemapProject('My Package', ProjectCategory::LaravelPackage, 'pkg');
    sitemapProject('My Article', ProjectCategory::Article, 'article-my-article');
    sitemapProject('A Site', ProjectCategory::Website, 'site-a-site');

    $this->artisan('sitemap:generate')->assertSuccessful();

    $projects = (string) file_get_contents(public_path('sitemap-projects.xml'));

    expect($projects)
        // canonical section per kind
        ->toContain('/projects/pkg')
        ->toContain('/articles/article-my-article')
        ->toContain('/links/site-a-site')
        // tiered priorities
        ->toContain('<priority>0.7</priority>') // code project
        ->toContain('<priority>0.6</priority>') // article
        ->toContain('<priority>0.5</priority>') // external link
        ->toContain('<changefreq>weekly</changefreq>')
        ->toContain('<changefreq>monthly</changefreq>');
});

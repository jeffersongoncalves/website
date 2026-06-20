<?php

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Jobs\MigrateBlogSitesToArticlesJob;
use App\Models\Project;
use App\Models\ProjectSlugAlias;

function runMigrateBlogSitesOp(): int
{
    return (new MigrateBlogSitesToArticlesJob)->handle();
}

function websiteProject(string $slug, string $name, string $docsUrl): Project
{
    return Project::query()->create([
        'slug' => $slug,
        'name' => $name,
        'category' => ProjectCategory::Website,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
        'docs_url' => $docsUrl,
    ]);
}

it('reclassifies a website with a blog-post path as an article and aliases the old slug', function () {
    $site = websiteProject('site-cool-post', 'Cool Post', 'https://example.test/blog/cool-post');

    runMigrateBlogSitesOp();
    $site->refresh();

    expect($site->category)->toBe(ProjectCategory::Article);
    expect($site->slug)->toBe('article-cool-post');
    expect(ProjectSlugAlias::query()
        ->where('slug', 'site-cool-post')
        ->where('project_id', $site->id)
        ->exists())->toBeTrue();

    // The retired website slug 301s to the new article URL.
    $this->get('/links/site-cool-post')
        ->assertRedirect(route('articles.show', ['slug' => 'article-cool-post']));
});

it('matches dated archive paths', function () {
    $site = websiteProject('site-dated', 'Dated', 'https://example.test/2024/05/my-post');

    runMigrateBlogSitesOp();

    expect($site->refresh()->category)->toBe(ProjectCategory::Article);
});

it('leaves a bare-root website as a website', function () {
    $site = websiteProject('site-homepage', 'Homepage', 'https://example.test/');

    runMigrateBlogSitesOp();

    expect($site->refresh()->category)->toBe(ProjectCategory::Website);
});

it('leaves a listing page (no post slug) as a website', function () {
    $site = websiteProject('site-blog-root', 'Blog Root', 'https://example.test/blog');

    runMigrateBlogSitesOp();

    expect($site->refresh()->category)->toBe(ProjectCategory::Website);
});

it('is idempotent — a second run converts nothing more', function () {
    websiteProject('site-post', 'Post', 'https://example.test/articles/post');

    runMigrateBlogSitesOp();
    runMigrateBlogSitesOp();

    expect(Project::query()->where('category', ProjectCategory::Article->value)->count())->toBe(1);
});

it('runs via the projects:migrate-blog-sites command', function () {
    websiteProject('site-cmd', 'Cmd', 'https://example.test/blog/cmd');

    $this->artisan('projects:migrate-blog-sites')->assertSuccessful();

    expect(Project::query()
        ->where('slug', 'article-cmd')
        ->where('category', ProjectCategory::Article->value)
        ->exists())->toBeTrue();
});

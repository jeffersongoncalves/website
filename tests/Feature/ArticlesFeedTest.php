<?php

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;

function makeArticle(array $overrides = []): Project
{
    return Project::query()->create(array_merge([
        'slug' => 'an-article',
        'name' => 'An Article',
        'category' => ProjectCategory::Article,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
    ], $overrides));
}

it('serves an RSS feed of published articles', function () {
    makeArticle(['slug' => 'feed-me', 'name' => 'Feed Me']);

    $this->get('/articles/feed')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
        ->assertSee('Feed Me')
        ->assertSee('feed-me');
});

it('excludes unpublished articles and non-article projects from the feed', function () {
    makeArticle(['slug' => 'visible', 'name' => 'Visible Article']);
    makeArticle(['slug' => 'draft', 'name' => 'Draft Article', 'status' => ProjectStatus::Draft, 'published_at' => null]);
    Project::query()->create([
        'slug' => 'a-tool',
        'name' => 'Not An Article',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
    ]);

    $this->get('/articles/feed')
        ->assertOk()
        ->assertSee('Visible Article')
        ->assertDontSee('Draft Article')
        ->assertDontSee('Not An Article');
});

<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Livewire\Site\ArticlesList;
use App\Models\Project;
use Illuminate\Support\Str;
use Livewire\Livewire;

function article(string $name, array $attrs = []): Project
{
    return Project::query()->create(array_merge([
        'slug' => Str::slug($name),
        'name' => $name,
        'category' => ProjectCategory::Article,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
    ], $attrs));
}

it('renders the /articles page with the list and feed link', function () {
    article('my-first-article');

    $this->get('/pt_BR/articles')
        ->assertOk()
        ->assertSee('my-first-article')
        ->assertSee(__('site.articles.title'))
        ->assertSee(route('articles.feed'));
});

it('lists published articles newest first and paginates', function () {
    foreach (range(1, 14) as $i) {
        article("article-{$i}", ['published_at' => now()->subDays($i)]);
    }

    Livewire::test(ArticlesList::class)
        ->assertSee('article-1')   // newest (subDays 1) on page 1
        ->assertDontSee('article-14') // 14th newest falls to page 2 (12/page)
        ->call('nextPage', 'page')
        ->assertSee('article-14');
});

it('keeps draft articles off the list', function () {
    article('published-one');
    article('hidden-draft', ['status' => ProjectStatus::Draft, 'published_at' => null]);

    Livewire::test(ArticlesList::class)
        ->assertSee('published-one')
        ->assertDontSee('hidden-draft');
});

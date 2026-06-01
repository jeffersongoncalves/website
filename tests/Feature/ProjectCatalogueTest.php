<?php

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Support\SiteStats;
use Illuminate\Support\Str;

function publishedProject(string $name, array $attrs = []): Project
{
    return Project::query()->create(array_merge([
        'slug' => Str::slug($name),
        'name' => $name,
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
    ], $attrs));
}

it('excludes articles from the projects catalogue', function () {
    publishedProject('a-real-tool');
    publishedProject('some-article', [
        'slug' => 'article-some-article',
        'category' => ProjectCategory::Article,
        'docs_url' => 'https://blog.test/some-article',
    ]);

    $this->get('/projects')
        ->assertOk()
        ->assertSee('a-real-tool')
        ->assertDontSee('some-article');

    // Even an explicit cat=article filter shows nothing in the catalogue.
    $this->get('/projects?cat=article')
        ->assertOk()
        ->assertDontSee('some-article');
});

it('finds projects by free-text search on name and repo', function () {
    publishedProject('alpha-widget', ['repo' => 'alpha-widget']);
    publishedProject('beta-gadget', ['repo' => 'beta-gadget']);

    $this->get('/projects?search=widget')
        ->assertOk()
        ->assertSee('alpha-widget')
        ->assertDontSee('beta-gadget');
});

it('escapes LIKE wildcards so _ is literal, not any-char', function () {
    publishedProject('a_c', ['slug' => 'lit-underscore', 'repo' => 'a_c']);
    publishedProject('abc', ['slug' => 'abc-repo', 'repo' => 'abc']);

    // With _ escaped, "a_c" matches only the literal a_c, not abc.
    $this->get('/projects?search=a_c')
        ->assertOk()
        ->assertSee('a_c')
        ->assertDontSee('abc');
});

it('shows the empty state when nothing matches', function () {
    publishedProject('something');

    $this->get('/projects?search=zzz-no-match')
        ->assertOk()
        ->assertSee(__('site.common.no_results'));
});

it('paginates and preserves filters on page 2', function () {
    for ($i = 1; $i <= 12; $i++) {
        publishedProject("plugin-{$i}", [
            'slug' => "plugin-{$i}",
            'category' => ProjectCategory::FilamentPlugin,
            'stars' => $i,
        ]);
    }

    $this->get('/projects?cat=filament_plugin')
        ->assertOk()
        // Page-2 link must carry the category filter through.
        ->assertSee('cat=filament_plugin', false)
        ->assertSee('page=2', false);

    $this->get('/projects?cat=filament_plugin&page=2')
        ->assertOk()
        ->assertSee('plugin-1'); // lowest stars lands on the last page
});

it('sorts by name, stars and downloads', function () {
    publishedProject('zeta', ['slug' => 'zeta', 'stars' => 1, 'downloads' => 999]);
    publishedProject('alpha', ['slug' => 'alpha', 'stars' => 50, 'downloads' => 1]);

    $byName = $this->get('/projects?sort=name')->getContent();
    expect(strpos($byName, 'alpha'))->toBeLessThan(strpos($byName, 'zeta'));

    $byStars = $this->get('/projects?sort=stars')->getContent();
    expect(strpos($byStars, 'alpha'))->toBeLessThan(strpos($byStars, 'zeta'));

    $byDownloads = $this->get('/projects?sort=downloads')->getContent();
    expect(strpos($byDownloads, 'zeta'))->toBeLessThan(strpos($byDownloads, 'alpha'));
});

it('filters by topic', function () {
    publishedProject('laravel-thing', ['slug' => 'laravel-thing', 'topics' => ['laravel', 'php']]);
    publishedProject('vue-thing', ['slug' => 'vue-thing', 'topics' => ['vue']]);

    $this->get('/projects?topic=laravel')
        ->assertOk()
        ->assertSee('laravel-thing')
        ->assertDontSee('vue-thing');
});

it('role=authored lists only repos under the owner account, not starred third-party repos', function () {
    config(['services.github.username' => 'jeffersongoncalves']);

    publishedProject('my-own-pkg', [
        'slug' => 'my-own-pkg',
        'github_url' => 'https://github.com/jeffersongoncalves/my-own-pkg',
        'is_maintainer' => false,
    ]);
    // A starred third-party repo: is_maintainer=false too, so the old
    // `where('is_maintainer', false)` scope wrongly listed it as authored.
    publishedProject('someone-else-repo', [
        'slug' => 'someone-else-repo',
        'github_url' => 'https://github.com/someoneelse/cool-thing',
        'is_maintainer' => false,
        'starred_at' => now(),
    ]);

    $this->get('/projects?role=authored')
        ->assertOk()
        ->assertSee('my-own-pkg')
        ->assertDontSee('someone-else-repo');
});

it('filters by language when the language is in the persisted facet', function () {
    publishedProject('php-lib', ['slug' => 'php-lib', 'category' => ProjectCategory::PhpPackage])->update(['language' => 'PHP']);
    publishedProject('js-lib', ['slug' => 'js-lib', 'category' => ProjectCategory::JavascriptPackage])->update(['language' => 'JavaScript']);

    // The language facet is validated against the persisted SiteStats breakdown.
    SiteStats::persist();

    $this->get('/projects?language=PHP')
        ->assertOk()
        ->assertSee('php-lib')
        ->assertDontSee('js-lib');
});

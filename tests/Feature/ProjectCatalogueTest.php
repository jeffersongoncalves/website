<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Livewire\Site\ProjectsList;
use App\Models\Project;
use App\Support\SiteStats;
use Illuminate\Support\Str;
use JeffersonGoncalves\LaravelPageVisits\Models\PageVisit;
use Livewire\Livewire;

function publishedProject(string $name, array $attrs = []): Project
{
    return createProject(array_merge([
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
    // Decoy uses 'x' (never a hex char) so the assertion can't false-match the
    // hex sha256 in Livewire's wire:snapshot checksum — 'abc' would flake there.
    publishedProject('axc', ['slug' => 'axc-repo', 'repo' => 'axc']);

    // With _ escaped, "a_c" matches only the literal a_c, not the any-char axc.
    $this->get('/projects?search=a_c')
        ->assertOk()
        ->assertSee('a_c')
        ->assertDontSee('axc');
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

    // Page 1 of the filtered catalogue shows the high-stars plugins (10/page).
    // (plugin-2 has stars=2 so it falls to page 2 — and isn't a substring of
    // any page-1 name, unlike plugin-1 which lives inside plugin-1{0,1,2}.)
    $this->get('/projects?cat=filament_plugin')
        ->assertOk()
        ->assertSee('plugin-12')
        ->assertDontSee('plugin-2');

    // Deep-linking ?page=2 (Livewire's WithPagination reads it on mount) still
    // applies the cat filter and lands on the lowest-stars plugins.
    $this->get('/projects?cat=filament_plugin&page=2')
        ->assertOk()
        ->assertSee('plugin-2'); // lowest stars land on the last page
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

it('sorts by trending (recent projects.show visits, bots excluded)', function () {
    publishedProject('quiet-one', ['slug' => 'quiet-one', 'stars' => 999]);
    publishedProject('hot-one', ['slug' => 'hot-one', 'stars' => 1]);

    PageVisit::query()->insert([
        ['path' => 'projects/hot-one', 'route_name' => 'projects.show', 'method' => 'GET', 'visited_at' => now(), 'is_bot' => false],
        ['path' => 'projects/hot-one', 'route_name' => 'projects.show', 'method' => 'GET', 'visited_at' => now(), 'is_bot' => false],
        // A bot hit on quiet-one must not count toward trending.
        ['path' => 'projects/quiet-one', 'route_name' => 'projects.show', 'method' => 'GET', 'visited_at' => now(), 'is_bot' => true],
        // Outside the 30-day trending window — must not count either.
        ['path' => 'projects/quiet-one', 'route_name' => 'projects.show', 'method' => 'GET', 'visited_at' => now()->subDays(31), 'is_bot' => false],
    ]);

    $byTrending = $this->get('/projects?sort=trending')->getContent();
    expect(strpos($byTrending, 'hot-one'))->toBeLessThan(strpos($byTrending, 'quiet-one'));
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

it('reacts to live search on the ProjectsList component', function () {
    publishedProject('alpha-widget', ['repo' => 'alpha-widget']);
    publishedProject('beta-gadget', ['repo' => 'beta-gadget']);

    Livewire::test(ProjectsList::class)
        ->assertSee('alpha-widget')
        ->assertSee('beta-gadget')
        ->set('search', 'widget')
        ->assertSee('alpha-widget')
        ->assertDontSee('beta-gadget');
});

it('toggles the topic filter via wire actions and clears it', function () {
    publishedProject('laravel-thing', ['slug' => 'laravel-thing', 'topics' => ['laravel']]);
    publishedProject('vue-thing', ['slug' => 'vue-thing', 'topics' => ['vue']]);

    Livewire::test(ProjectsList::class)
        ->call('setTopic', 'laravel')
        ->assertSet('topic', 'laravel')
        ->assertSee('laravel-thing')
        ->assertDontSee('vue-thing')
        ->call('clearTopic')
        ->assertSet('topic', '')
        ->assertSee('vue-thing');
});

it('resets to page 1 when a facet changes', function () {
    for ($i = 1; $i <= 12; $i++) {
        publishedProject("plugin-{$i}", [
            'slug' => "plugin-{$i}",
            'category' => ProjectCategory::FilamentPlugin,
            'stars' => $i,
        ]);
    }

    Livewire::test(ProjectsList::class)
        ->set('cat', 'filament_plugin')
        ->call('nextPage', 'page')
        ->assertSet('paginators.page', 2)
        // Changing a facet on page 2 snaps back to page 1.
        ->set('sort', 'name')
        ->assertSet('paginators.page', 1);
});

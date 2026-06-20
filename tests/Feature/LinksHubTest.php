<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Livewire\Site\LinksSection;
use App\Models\Project;
use Illuminate\Support\Str;
use Livewire\Livewire;

function externalLink(string $name, ProjectCategory $category, array $attrs = []): Project
{
    return Project::query()->create(array_merge([
        'slug' => Str::slug($name),
        'name' => $name,
        'category' => $category,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
        'docs_url' => 'https://example.test/'.Str::slug($name),
    ], $attrs));
}

it('shows external links grouped on the /links hub', function () {
    externalLink('my-fav-site', ProjectCategory::Website);
    externalLink('a-channel', ProjectCategory::YoutubeChannel, [
        'docs_url' => 'https://www.youtube.com/@a-channel',
    ]);
    externalLink('a-course', ProjectCategory::LearningResource);
    externalLink('awesome-things', ProjectCategory::AwesomeList);

    $this->get('/links')
        ->assertOk()
        ->assertSee('my-fav-site')
        ->assertSee('a-channel')
        ->assertSee('a-course')
        ->assertSee('awesome-things')
        ->assertSee(__('site.links.section_website'))
        ->assertSee(__('site.links.section_youtube_channel'));
});

it('invalidates the cached /links sections + topics when a project is published', function () {
    // Warm the section + topic caches while the channel does not exist yet.
    $this->get('/links')
        ->assertOk()
        ->assertDontSee('fresh-channel');

    // Publishing a channel flushes both caches via ProjectObserver. If the
    // SECTIONS_CACHE were stale the section wouldn't render at all, and a stale
    // topics cache would drop the chip — so seeing both proves invalidation.
    externalLink('fresh-channel', ProjectCategory::YoutubeChannel, [
        'docs_url' => 'https://www.youtube.com/@fresh-channel',
        'topics' => ['streaming'],
    ]);

    $this->get('/links')
        ->assertOk()
        ->assertSee('fresh-channel')
        ->assertSee('streaming');
});

it('keeps external links out of the code catalogue at /projects', function () {
    Project::query()->create([
        'slug' => 'a-real-tool',
        'name' => 'a-real-tool',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
    ]);
    externalLink('external-site', ProjectCategory::Website);

    $this->get('/projects')
        ->assertOk()
        ->assertSee('a-real-tool')
        ->assertDontSee('external-site');

    // An explicit external-category filter shows nothing in the catalogue.
    $this->get('/projects?cat=website')
        ->assertOk()
        ->assertDontSee('external-site');
});

it('serves external links under /links/{slug} with a back link to the right section', function () {
    externalLink('my-fav-site', ProjectCategory::Website);

    $this->get('/links/my-fav-site')
        ->assertOk()
        ->assertSee('my-fav-site')
        ->assertSee(__('site.links.back_to_list'))
        // Back link returns to the Sites section anchor on /links.
        ->assertSee(route('links.index').'#sites', false);
});

it('301s an external link served under /projects to its /links section', function () {
    externalLink('external-site', ProjectCategory::Website);

    $this->get('/projects/external-site')
        ->assertRedirect(route('links.show', ['slug' => 'external-site']));
});

it('301s a code project served under /links back to /projects', function () {
    Project::query()->create([
        'slug' => 'a-real-tool',
        'name' => 'a-real-tool',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
    ]);

    $this->get('/links/a-real-tool')
        ->assertRedirect(route('projects.show', ['slug' => 'a-real-tool']));
});

it('searches within a single section without touching the others', function () {
    externalLink('alpha-site', ProjectCategory::Website);
    externalLink('beta-site', ProjectCategory::Website);
    externalLink('alpha-channel', ProjectCategory::YoutubeChannel, [
        'docs_url' => 'https://www.youtube.com/@alpha-channel',
    ]);

    $this->get('/links?q_sites=alpha')
        ->assertOk()
        ->assertSee('alpha-site')
        ->assertDontSee('beta-site')
        // The YouTube section is untouched by the Sites search.
        ->assertSee('alpha-channel');
});

it('sorts a section by name descending', function () {
    externalLink('aaa-site', ProjectCategory::Website);
    externalLink('zzz-site', ProjectCategory::Website);

    $html = $this->get('/links?sort_sites=desc')->getContent();

    expect(strpos($html, 'zzz-site'))->toBeLessThan(strpos($html, 'aaa-site'));
});

it('filters a section by topic and points card topic links into /links', function () {
    externalLink('topic-site', ProjectCategory::Website, ['topics' => ['design']]);
    externalLink('plain-site', ProjectCategory::Website);

    // The card's topic chip links into the Sites section, not /projects.
    $this->get('/links')
        ->assertOk()
        ->assertSee('topic_sites=design', false);

    $this->get('/links?topic_sites=design')
        ->assertOk()
        ->assertSee('topic-site')
        ->assertDontSee('plain-site');
});

it('paginates each link group independently', function () {
    for ($i = 1; $i <= 8; $i++) {
        externalLink(sprintf('site-%02d', $i), ProjectCategory::Website);
    }
    for ($i = 1; $i <= 8; $i++) {
        externalLink(sprintf('chan-%02d', $i), ProjectCategory::YoutubeChannel, [
            'docs_url' => "https://www.youtube.com/@chan-{$i}",
        ]);
    }

    // Each section shows its own page 1 (PER_PAGE = 6); Livewire paginates via
    // wire:click, so the page number lives in the action, not an href.
    $this->get('/links')
        ->assertOk()
        ->assertSee('site-01')   // page 1 of the Sites group
        ->assertDontSee('site-07') // page 2 only
        ->assertSee('chan-01')   // page 1 of the YouTube group
        ->assertDontSee('chan-07');

    // Paging the Sites group must not move the YouTube group off its page 1.
    $this->get('/links?sites=2')
        ->assertOk()
        ->assertSee('site-07')   // Sites now on page 2
        ->assertDontSee('site-01')
        ->assertSee('chan-01')   // YouTube untouched, still page 1
        ->assertDontSee('chan-07');
});

it('drives a section search and topic filter through wire actions', function () {
    externalLink('alpha-site', ProjectCategory::Website, ['topics' => ['design']]);
    externalLink('beta-site', ProjectCategory::Website);

    Livewire::test(LinksSection::class, ['category' => ProjectCategory::Website, 'index' => 0])
        ->assertSee('alpha-site')
        ->assertSee('beta-site')
        ->set('search', 'alpha')
        ->assertSee('alpha-site')
        ->assertDontSee('beta-site')
        ->set('search', '')
        ->call('setTopic', 'design')
        ->assertSet('topic', 'design')
        ->assertSee('alpha-site')
        ->assertDontSee('beta-site')
        ->call('clearTopic')
        ->assertSet('topic', '')
        ->assertSee('beta-site');
});

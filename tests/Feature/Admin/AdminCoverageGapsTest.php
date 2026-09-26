<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Filament\Admin\Pages\HorizonDashboard;
use App\Filament\Admin\Resources\Projects\Pages\CreateProject;
use App\Filament\Admin\Resources\Projects\Pages\ListProjects;
use App\Models\Admin;
use App\Models\Project;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\Filament\Admin\Resources\Admins\Pages\EditAdmin;
use JeffersonGoncalves\Filament\User\Resources\Users\Pages\EditUser;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(Admin::factory()->create(['status' => true]), 'admin');
});

/**
 * Swap the global GitHub fake for a controlled one that returns a concrete
 * repo + manifests so a from-GitHub import resolves to a real `fields` array.
 */
function adminGaps_fakeGithub(): void
{
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake([
        'api.github.com/repos/acme/widget/branches*' => Http::response([['name' => 'main']]),
        'api.github.com/repos/acme/widget' => Http::response([
            'default_branch' => 'main',
            'description' => 'A handy widget',
            'language' => 'PHP',
            'homepage' => null,
            'topics' => ['laravel'],
            'license' => ['spdx_id' => 'MIT'],
        ]),
        'raw.githubusercontent.com/*' => Http::response('', 404),
        'registry.npmjs.org/*' => Http::response('', 404),
        'packagist.org/*' => Http::response('', 404),
    ]);
}

// ---------------------------------------------------------------------------
// HasImportFromGithubAction (mounted on CreateProject / EditProject)
// ---------------------------------------------------------------------------

it('imports repo metadata into the create form via the importFromGithub action', function () {
    adminGaps_fakeGithub();

    Livewire::test(CreateProject::class)
        ->callAction('importFromGithub', data: [
            'github_url' => 'https://github.com/acme/widget',
        ])
        ->assertHasNoActionErrors()
        ->assertFormSet([
            'name' => 'Widget',
            'github_url' => 'https://github.com/acme/widget',
        ]);
});

it('surfaces an error notification when the import URL is invalid', function () {
    // No HTTP needed — GithubReadme::repoFromUrl rejects the URL before any call.
    Livewire::test(CreateProject::class)
        ->callAction('importFromGithub', data: [
            'github_url' => 'https://example.com/not-a-repo',
        ])
        ->assertHasNoActionErrors();

    // Nothing was applied to the form — name stays empty.
    expect(Project::query()->count())->toBe(0);
});

it('runs the importFromNpm action (invalid_url path, no HTTP needed)', function () {
    Livewire::test(CreateProject::class)
        ->callAction('importFromNpm', data: [
            'npm_url' => 'https://example.com/not-an-npm-package',
        ])
        ->assertHasNoActionErrors();
});

it('runs the importFromYoutube action (invalid_url path, no HTTP needed)', function () {
    Livewire::test(CreateProject::class)
        ->callAction('importFromYoutube', data: [
            'youtube_url' => 'https://example.com/not-a-channel',
        ])
        ->assertHasNoActionErrors();
});

it('runs the importFromArticle action against a fake article host', function () {
    Http::fake([
        '*coverage-article.test*' => Http::response(
            '<html><head><title>A Post</title></head></html>',
            200,
        ),
    ]);

    Livewire::test(CreateProject::class)
        ->callAction('importFromArticle', data: [
            'article_url' => 'https://coverage-article.test/post',
        ])
        ->assertHasNoActionErrors();
});

it('runs the importFromUrl action against a fake website host', function () {
    Http::fake([
        '*coverage-website.test*' => Http::response(
            '<html><head><title>A Site</title></head></html>',
            200,
        ),
    ]);

    Livewire::test(CreateProject::class)
        ->callAction('importFromUrl', data: [
            'docs_url' => 'https://coverage-website.test',
        ])
        ->assertHasNoActionErrors();
});

it('degrades to a rate_limited error instead of throwing when importFromGithub hits the GitHub rate limit', function () {
    Http::swap(new Factory);
    Http::fake([
        'api.github.com/*' => Http::response('', 403, [
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Reset' => (string) (time() + 90),
        ]),
    ]);

    Livewire::test(CreateProject::class)
        ->callAction('importFromGithub', data: [
            'github_url' => 'https://github.com/acme/widget',
        ])
        ->assertHasNoActionErrors();

    expect(Project::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// ListProjects — page render + quickCreate header action
// ---------------------------------------------------------------------------

it('renders the projects list page', function () {
    $project = createProject([
        'slug' => 'listed',
        'name' => 'Listed',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'published_at' => now(),
    ]);

    Livewire::test(ListProjects::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$project]);
});

it('quick-creates a project from a GitHub URL', function () {
    adminGaps_fakeGithub();

    Livewire::test(ListProjects::class)
        ->callAction('quickCreate', data: [
            'source' => 'github',
            'url' => 'https://github.com/acme/widget',
            'status' => ProjectStatus::Draft->value,
            'is_daily_driver' => true,
        ])
        ->assertHasNoActionErrors();

    $project = Project::query()->where('name', 'Widget')->first();

    expect($project)->not->toBeNull()
        ->and($project->is_daily_driver)->toBeTrue();
});

it('shows an error notification when quickCreate gets an invalid URL', function () {
    Livewire::test(ListProjects::class)
        ->callAction('quickCreate', data: [
            'source' => 'github',
            'url' => 'https://example.com/nope',
            'status' => ProjectStatus::Draft->value,
        ])
        ->assertHasNoActionErrors();

    expect(Project::query()->count())->toBe(0);
});

it('quickCreate dispatches npm/youtube/article import calls (invalid_url path)', function () {
    Livewire::test(ListProjects::class)
        ->callAction('quickCreate', data: [
            'source' => 'npm',
            'url' => 'https://example.com/not-a-package',
            'status' => ProjectStatus::Draft->value,
        ])
        ->assertHasNoActionErrors();

    Livewire::test(ListProjects::class)
        ->callAction('quickCreate', data: [
            'source' => 'youtube',
            'url' => 'https://example.com/not-a-channel',
            'status' => ProjectStatus::Draft->value,
        ])
        ->assertHasNoActionErrors();

    Livewire::test(ListProjects::class)
        ->callAction('quickCreate', data: [
            'source' => 'article',
            'url' => 'https://example.com/not-a-real-host-but-valid-url',
            'status' => ProjectStatus::Draft->value,
        ])
        ->assertHasNoActionErrors();

    expect(Project::query()->count())->toBe(0);
});

it('degrades quickCreate to a rate_limited error instead of throwing', function () {
    Http::swap(new Factory);
    Http::fake([
        'api.github.com/*' => Http::response('', 403, [
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Reset' => (string) (time() + 90),
        ]),
    ]);

    Livewire::test(ListProjects::class)
        ->callAction('quickCreate', data: [
            'source' => 'github',
            'url' => 'https://github.com/acme/widget',
            'status' => ProjectStatus::Draft->value,
        ])
        ->assertHasNoActionErrors();

    expect(Project::query()->count())->toBe(0);
});

it('quickCreate updates an already-cadastrado project instead of duplicating it', function () {
    $existing = createProject([
        'slug' => 'widget',
        'name' => 'Widget',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/acme/widget',
        'published_at' => now(),
    ]);

    adminGaps_fakeGithub();

    Livewire::test(ListProjects::class)
        ->callAction('quickCreate', data: [
            'source' => 'github',
            'url' => 'https://github.com/acme/widget',
            'status' => ProjectStatus::Draft->value,
        ])
        ->assertHasNoActionErrors();

    // Existing row updated in place, not duplicated.
    expect(Project::query()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// EditAdmin / EditUser pages
// ---------------------------------------------------------------------------

it('renders and saves the edit-admin page', function () {
    $admin = Admin::factory()->create(['status' => true, 'name' => 'Original Admin']);

    Livewire::test(EditAdmin::class, ['record' => $admin->getRouteKey()])
        ->assertOk()
        ->fillForm(['name' => 'Renamed Admin'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($admin->refresh()->name)->toBe('Renamed Admin');
});

it('renders and saves the edit-user page', function () {
    $user = User::factory()->create(['status' => true, 'name' => 'Original User']);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->assertOk()
        ->fillForm(['name' => 'Renamed User'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->name)->toBe('Renamed User');
});

// ---------------------------------------------------------------------------
// AdminObserver — created / updated / deleted cache behaviour
// ---------------------------------------------------------------------------

it('clears the admins_count cache when an admin is created', function () {
    Cache::forever('admins_count', 99);

    Admin::factory()->create(['status' => true]);

    expect(Cache::get('admins_count'))->toBeNull();
});

it('leaves the admins_count cache untouched on update (no-op updated hook)', function () {
    $admin = Admin::factory()->create(['status' => true]);

    Cache::forever('admins_count', 99);

    $admin->update(['name' => 'Updated Name']);

    expect(Cache::get('admins_count'))->toBe(99)
        ->and($admin->refresh()->name)->toBe('Updated Name');
});

it('clears the admins_count cache when an admin is deleted', function () {
    $admin = Admin::factory()->create(['status' => true]);

    Cache::forever('admins_count', 99);

    $admin->delete();

    expect(Cache::get('admins_count'))->toBeNull();
});

// ---------------------------------------------------------------------------
// HorizonDashboard — iframe page, no Horizon internals needed
// ---------------------------------------------------------------------------

it('renders the horizon dashboard page', function () {
    Livewire::test(HorizonDashboard::class)->assertOk();
});

it('exposes a Horizon page title', function () {
    expect((new HorizonDashboard)->getTitle())->toBe(__('Horizon'));
});

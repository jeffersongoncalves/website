<?php

declare(strict_types=1);

use App\Enums\PackageType;
use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Filament\Admin\Resources\Projects\Pages\CreateProject;
use App\Filament\Admin\Resources\Projects\Pages\EditProject;
use App\Filament\Admin\Resources\Projects\Pages\ViewProject;
use App\Filament\Admin\Widgets\SiteCategoriesWidget;
use App\Filament\Admin\Widgets\SiteDownloadsWidget;
use App\Filament\Admin\Widgets\SiteLanguagesWidget;
use App\Filament\Admin\Widgets\SiteMetricsWidget;
use App\Filament\Admin\Widgets\SiteTopicsWidget;
use App\Models\Admin;
use App\Models\Project;
use App\Models\SiteStat;
use App\Models\User;
use Filament\Facades\Filament;
use JeffersonGoncalves\Filament\Admin\Resources\Admins\Pages\ViewAdmin;
use JeffersonGoncalves\Filament\User\Resources\Users\Pages\ViewUser;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(Admin::factory()->create(['status' => true]), 'admin');
});

/**
 * Seed the singleton SiteStat row the widgets read through SiteStats::all(),
 * including non-empty languages/topics so the gated widgets pass canView().
 */
function filamentSchemas_seedSiteStat(): SiteStat
{
    return SiteStat::query()->create([
        'repos' => 42,
        'catalogue' => 100,
        'filament' => 7,
        'laravel' => 5,
        'livewire' => 3,
        'cakephp' => 1,
        'laravel_zero' => 1,
        'ide_plugin' => 2,
        'framework' => 1,
        'starter' => 4,
        'saas' => 1,
        'tool' => 6,
        'docker' => 2,
        'database' => 1,
        'website' => 1,
        'youtube_channel' => 1,
        'php_package' => 8,
        'javascript_package' => 3,
        'css_framework' => 1,
        'application' => 2,
        'learning_resource' => 1,
        'awesome_list' => 1,
        'mobile_library' => 1,
        'maintained' => 20,
        'daily_drivers' => 5,
        'stars' => 1234,
        'downloads' => 2_500_000,
        'downloads_packagist' => 2_000_000,
        'downloads_npm' => 400_000,
        'downloads_jetbrains' => 50_000,
        'downloads_docker' => 50_000,
        'followers' => 1500,
        'public_sponsors' => 9,
        'contributions' => ['cells' => [1, 2, 3], 'total' => 321],
        'languages' => [
            ['language' => 'PHP', 'total' => 30],
            ['language' => 'Blade', 'total' => 10],
            ['language' => 'SomethingUnmapped', 'total' => 2],
        ],
        'topics' => [
            ['topic' => 'laravel', 'total' => 15],
            ['topic' => 'filament', 'total' => 12],
        ],
        'synced_at' => now(),
    ]);
}

it('creates a project through the form schema', function () {
    Livewire::test(CreateProject::class)
        ->fillForm([
            'name' => 'Coverage Tool',
            'slug' => 'coverage-tool',
            'category' => ProjectCategory::Tool->value,
            'package_type' => PackageType::Composer->value,
            'status' => ProjectStatus::Published->value,
            'featured' => true,
            'is_maintainer' => true,
            'is_daily_driver' => true,
            'github_url' => 'https://github.com/acme/coverage-tool',
            'docs_url' => 'https://example.test/docs',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Project::query()->where('slug', 'coverage-tool')->exists())->toBeTrue();
});

it('exercises the filament-plugin branch-tracking fields on create', function () {
    // Drives the category-gated has_branches / versions / branch_overrides
    // visibility closures and the versions afterStateUpdated callback.
    Livewire::test(CreateProject::class)
        ->fillForm([
            'name' => 'Filament Coverage Plugin',
            'slug' => 'filament-coverage-plugin',
            'category' => ProjectCategory::FilamentPlugin->value,
            'status' => ProjectStatus::Published->value,
            'is_paid' => false,
            'has_branches' => true,
        ])
        ->fillForm(['versions' => ['v3', 'v4']])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Project::query()->where('slug', 'filament-coverage-plugin')->exists())->toBeTrue();
});

it('hydrates the edit form schema from an existing project', function () {
    $project = createProject([
        'slug' => 'edit-me',
        'name' => 'Edit Me',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'package_type' => PackageType::Composer,
        'featured' => true,
        'is_maintainer' => true,
        'github_url' => 'https://github.com/acme/edit-me',
        'published_at' => now(),
    ]);

    Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
        ->assertOk()
        ->assertFormSet([
            'name' => 'Edit Me',
            'slug' => 'edit-me',
            'category' => ProjectCategory::FilamentPlugin,
            'featured' => true,
            'github_url' => 'https://github.com/acme/edit-me',
        ]);
});

it('auto-fills branch_overrides from versions on hydrate when none is stored yet', function () {
    $project = createProject([
        'slug' => 'auto-branches',
        'name' => 'Auto Branches',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'has_branches' => true,
        'versions' => ['v3', 'v4'],
        'branch_overrides' => null,
        'published_at' => now(),
    ]);

    Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
        ->assertOk()
        ->assertFormSet(['branch_overrides' => ['1.x' => '1.x', '2.x' => '2.x']]);
});

it('leaves an already-stored branch_overrides map alone on hydrate', function () {
    $project = createProject([
        'slug' => 'manual-branches',
        'name' => 'Manual Branches',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'has_branches' => true,
        'versions' => ['v3', 'v4'],
        'branch_overrides' => ['1.x' => 'legacy-branch'],
        'published_at' => now(),
    ]);

    Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
        ->assertOk()
        ->assertFormSet(['branch_overrides' => ['1.x' => 'legacy-branch']]);
});

it('renders the project infolist on the view page', function () {
    $project = createProject([
        'slug' => 'view-me',
        'name' => 'View Me',
        'category' => ProjectCategory::LaravelPackage,
        'status' => ProjectStatus::Published,
        'stars' => 99,
        'downloads' => 12345,
        'github_url' => 'https://github.com/acme/view-me',
        'versions' => ['v4'],
        'stack' => ['Laravel', 'Filament'],
        'published_at' => now(),
    ]);

    Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
        ->assertOk();
});

it('renders the admin infolist on the view page', function () {
    $admin = Admin::factory()->create(['status' => true]);

    Livewire::test(ViewAdmin::class, ['record' => $admin->getKey()])
        ->assertOk();
});

it('renders the user infolist on the view page', function () {
    $user = User::factory()->create(['status' => true]);

    Livewire::test(ViewUser::class, ['record' => $user->getKey()])
        ->assertOk();
});

it('renders the site metrics widget', function () {
    filamentSchemas_seedSiteStat();

    Livewire::test(SiteMetricsWidget::class)->assertOk();
});

it('formats followers in the metrics widget across the compact-number branches (plain, k, M)', function () {
    filamentSchemas_seedSiteStat();
    SiteStat::query()->update(['followers' => 42]);
    Livewire::test(SiteMetricsWidget::class)->assertOk();

    SiteStat::query()->update(['followers' => 2_500_000]);
    Livewire::test(SiteMetricsWidget::class)->assertOk();
});

it('renders the site downloads widget', function () {
    filamentSchemas_seedSiteStat();

    Livewire::test(SiteDownloadsWidget::class)->assertOk();
});

it('formats a small download count in the downloads widget (plain-number branch)', function () {
    filamentSchemas_seedSiteStat();
    SiteStat::query()->update(['downloads_docker' => 7]);

    Livewire::test(SiteDownloadsWidget::class)->assertOk();
});

it('renders the site categories widget', function () {
    filamentSchemas_seedSiteStat();

    Livewire::test(SiteCategoriesWidget::class)->assertOk();
});

it('renders the site languages widget', function () {
    filamentSchemas_seedSiteStat();

    expect(SiteLanguagesWidget::canView())->toBeTrue();

    Livewire::test(SiteLanguagesWidget::class)->assertOk();
});

it('renders the site topics widget', function () {
    filamentSchemas_seedSiteStat();

    expect(SiteTopicsWidget::canView())->toBeTrue();

    Livewire::test(SiteTopicsWidget::class)->assertOk();
});

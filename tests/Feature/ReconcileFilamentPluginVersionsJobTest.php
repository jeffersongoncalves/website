<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Jobs\ReconcileFilamentPluginVersionsJob;
use App\Models\Project;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    // The global Pest fake stubs raw.githubusercontent.com/* with a README
    // fixture that would shadow the per-branch composer.json stubs below —
    // drop the resolved factory so each test's exact-URL stubs win.
    app()->forgetInstance(HttpFactory::class);
    Http::clearResolvedInstances();
    Http::preventStrayRequests();
});

function reconcile(int $id): void
{
    (new ReconcileFilamentPluginVersionsJob($id))->handle();
}

it('unions versions from every N.x branch and promotes has_branches', function (): void {
    $project = Project::query()->create([
        'slug' => 'jeffersongoncalves-filament-page-visits',
        'name' => 'Filament Page Visits',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/filament-page-visits',
        'versions' => ['v5'],
        'has_branches' => false,
    ]);

    Http::fake([
        'api.github.com/repos/*/branches*' => Http::response([
            ['name' => '1.x'], ['name' => '2.x'], ['name' => '3.x'],
        ]),
        'raw.githubusercontent.com/jeffersongoncalves/filament-page-visits/1.x/composer.json' => Http::response(['require' => ['filament/filament' => '^3.0']]),
        'raw.githubusercontent.com/jeffersongoncalves/filament-page-visits/2.x/composer.json' => Http::response(['require' => ['filament/filament' => '^4.0']]),
        'raw.githubusercontent.com/jeffersongoncalves/filament-page-visits/3.x/composer.json' => Http::response(['require' => ['filament/filament' => '^5.0']]),
    ]);

    reconcile($project->id);

    $project->refresh();
    expect($project->versions)->toBe(['v3', 'v4', 'v5'])
        ->and($project->has_branches)->toBeTrue();
});

it('never removes a previously-stored version even if a branch fetch comes back empty', function (): void {
    $project = Project::query()->create([
        'slug' => 'jeffersongoncalves-filament-example',
        'name' => 'Filament Example',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/filament-example',
        'versions' => ['v3', 'v4', 'v5'],
        'has_branches' => true,
    ]);

    Http::fake([
        'api.github.com/repos/*/branches*' => Http::response([['name' => '1.x']]),
        'raw.githubusercontent.com/*/composer.json' => Http::response('', 404),
    ]);

    reconcile($project->id);

    expect($project->refresh()->versions)->toBe(['v3', 'v4', 'v5']);
});

it('does nothing for a project with no version branches', function (): void {
    $project = Project::query()->create([
        'slug' => 'jeffersongoncalves-filament-single-branch',
        'name' => 'Filament Single Branch',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/filament-single-branch',
        'versions' => ['v5'],
        'has_branches' => false,
    ]);

    Http::fake([
        'api.github.com/repos/*/branches*' => Http::response([['name' => 'main']]),
    ]);

    reconcile($project->id);

    $project->refresh();
    expect($project->versions)->toBe(['v5'])
        ->and($project->has_branches)->toBeFalse();
});

it('does nothing when the project or its github_url is missing', function (): void {
    reconcile(999999);

    $project = Project::query()->create([
        'slug' => 'jeffersongoncalves-no-github-url',
        'name' => 'No Github Url',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
        'github_url' => null,
    ]);

    reconcile($project->id);

    expect($project->refresh()->versions)->toBeNull();
});

it('logs context when the job fails', function (): void {
    Log::shouldReceive('error')->once()->with('ReconcileFilamentPluginVersionsJob failed', [
        'project_id' => 1,
        'error' => 'boom',
    ]);

    (new ReconcileFilamentPluginVersionsJob(1))->failed(new Exception('boom'));
});

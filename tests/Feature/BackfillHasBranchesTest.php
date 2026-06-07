<?php

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;

function runBackfillHasBranchesOp(): void
{
    (require base_path('operations/2026_06_07_130000_backfill_has_branches_for_filament_plugins.php'))->process();
}

function backfillPlugin(array $attrs = []): Project
{
    return Project::query()->create(array_merge([
        'name' => 'Plugin',
        'category' => ProjectCategory::FilamentPlugin,
        'status' => ProjectStatus::Published,
    ], $attrs));
}

it('turns on has_branches for non-paid plugins that already track versions', function () {
    $tracked = backfillPlugin(['slug' => 'tracked', 'versions' => ['v3', 'v4']]);
    $noVersions = backfillPlugin(['slug' => 'no-versions', 'versions' => []]);
    $paid = backfillPlugin(['slug' => 'paid', 'versions' => ['v3'], 'is_paid' => true]);
    $nonPlugin = Project::query()->create([
        'name' => 'Pkg',
        'slug' => 'pkg',
        'category' => ProjectCategory::LaravelPackage,
        'status' => ProjectStatus::Published,
        'versions' => ['v3'],
    ]);

    runBackfillHasBranchesOp();

    expect($tracked->fresh()->has_branches)->toBeTrue()
        ->and($noVersions->fresh()->has_branches)->toBeFalse()
        ->and($paid->fresh()->has_branches)->toBeFalse()
        ->and($nonPlugin->fresh()->has_branches)->toBeFalse();
});

<?php

use App\Enums\PackageType;
use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Jobs\PurgeMisattributedPackagistUrlJob;
use App\Models\Project;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    // The global Pest fake stubs packagist.org/* with a downloads payload that
    // shadows the ownership lookup — drop the resolved factory so each test's
    // packagist stub wins.
    app()->forgetInstance(HttpFactory::class);
    Http::clearResolvedInstances();
    Http::preventStrayRequests();
});

function packagistProject(): Project
{
    return Project::query()->create([
        'slug' => 'borrowed-name',
        'name' => 'Borrowed Name',
        'category' => ProjectCategory::LaravelPackage,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/savanihd/Laravel-11-Livewire-CRUD',
        'packagist_url' => 'https://packagist.org/packages/laravel/laravel',
        // package_type + download metrics derived from the wrong package.
        'package_type' => PackageType::Composer,
        'downloads' => 123456789,
        'downloads_label' => '123M',
    ]);
}

it('purges a foreign packagist_url and clears the package_type + download metrics', function (): void {
    $project = packagistProject();

    Http::fake(['packagist.org/*' => Http::response(['package' => ['repository' => 'https://github.com/laravel/laravel']], 200)]);

    (new PurgeMisattributedPackagistUrlJob($project->id))->handle();

    $fresh = $project->fresh();
    expect($fresh->packagist_url)->toBeNull()
        ->and($fresh->package_type)->toBe(PackageType::None)
        ->and($fresh->downloads)->toBe(0)
        ->and($fresh->downloads_label)->toBeNull();
});

it('purges a packagist_url for an unpublished package (404)', function (): void {
    $project = packagistProject();

    Http::fake(['packagist.org/*' => Http::response('', 404)]);

    (new PurgeMisattributedPackagistUrlJob($project->id))->handle();

    $fresh = $project->fresh();
    expect($fresh->packagist_url)->toBeNull()
        ->and($fresh->package_type)->toBe(PackageType::None)
        ->and($fresh->downloads)->toBe(0)
        ->and($fresh->downloads_label)->toBeNull();
});

it('keeps the packagist_url when Packagist rate-limits the check (429)', function (): void {
    $project = packagistProject();

    Http::fake(['packagist.org/*' => Http::response('', 429)]);

    // release() is a no-op when the job has no queue instance, so the row is
    // simply left untouched for a later retry.
    (new PurgeMisattributedPackagistUrlJob($project->id))->handle();

    expect($project->fresh()->packagist_url)->toBe('https://packagist.org/packages/laravel/laravel');
});

it('keeps a packagist_url the repo genuinely owns', function (): void {
    $project = Project::query()->create([
        'slug' => 'real-pkg',
        'name' => 'Real Pkg',
        'category' => ProjectCategory::LaravelPackage,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/filament-gtag',
        'packagist_url' => 'https://packagist.org/packages/jeffersongoncalves/filament-gtag',
        'package_type' => PackageType::Composer,
        'downloads' => 4200,
        'downloads_label' => '4.2k',
    ]);

    Http::fake(['packagist.org/*' => Http::response(['package' => ['repository' => 'https://github.com/jeffersongoncalves/filament-gtag']], 200)]);

    (new PurgeMisattributedPackagistUrlJob($project->id))->handle();

    // Owned → nothing touched, including package_type + legitimate metrics.
    $fresh = $project->fresh();
    expect($fresh->packagist_url)->toBe('https://packagist.org/packages/jeffersongoncalves/filament-gtag')
        ->and($fresh->package_type)->toBe(PackageType::Composer)
        ->and($fresh->downloads)->toBe(4200)
        ->and($fresh->downloads_label)->toBe('4.2k');
});

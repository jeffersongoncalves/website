<?php

declare(strict_types=1);

use App\Enums\PackageType;
use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Jobs\PurgeMisattributedPackageLinksJob;
use App\Models\Project;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    // The global Pest fake stubs packagist.org/* and api.npmjs.org/* with
    // download payloads that shadow the ownership lookups — drop the resolved
    // factory so each test's registry stubs win.
    app()->forgetInstance(HttpFactory::class);
    Http::clearResolvedInstances();
    Http::preventStrayRequests();
});

function purge(int $id): void
{
    (new PurgeMisattributedPackageLinksJob($id))->handle();
}

it('purges a foreign packagist link and clears package_type + downloads', function (): void {
    $project = Project::query()->create([
        'slug' => 'borrowed-composer',
        'name' => 'Borrowed Composer',
        'category' => ProjectCategory::LaravelPackage,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/savanihd/Laravel-11-Livewire-CRUD',
        'packagist_url' => 'https://packagist.org/packages/laravel/laravel',
        'package_type' => PackageType::Composer,
        'downloads' => 123456789,
        'downloads_label' => '123M',
    ]);

    Http::fake(['packagist.org/*' => Http::response(['package' => ['repository' => 'https://github.com/laravel/laravel']], 200)]);

    purge($project->id);

    $fresh = $project->fresh();
    expect($fresh->packagist_url)->toBeNull()
        ->and($fresh->package_type)->toBe(PackageType::None)
        ->and($fresh->downloads)->toBe(0)
        ->and($fresh->downloads_label)->toBeNull();
});

it('purges a foreign npm link (mautic) — package not from the same repo', function (): void {
    $project = Project::query()->create([
        'slug' => 'mautic-mautic',
        'name' => 'Mautic',
        'category' => ProjectCategory::Application,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/mautic/mautic',
        'npm_url' => 'https://www.npmjs.com/package/mautic',
        'package_type' => PackageType::Npm,
        'downloads' => 9999,
        'downloads_label' => '9.9k',
    ]);

    // npm `mautic` exists but its repository is a different repo.
    Http::fake(['registry.npmjs.org/*' => Http::response(['repository' => ['url' => 'https://github.com/someone/else']], 200)]);

    purge($project->id);

    $fresh = $project->fresh();
    expect($fresh->npm_url)->toBeNull()
        ->and($fresh->package_type)->toBe(PackageType::None)
        ->and($fresh->downloads)->toBe(0)
        ->and($fresh->downloads_label)->toBeNull();
});

it('purges an npm link for an unpublished package (404)', function (): void {
    $project = Project::query()->create([
        'slug' => 'no-npm',
        'name' => 'No Npm',
        'category' => ProjectCategory::Application,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/acme/app',
        'npm_url' => 'https://www.npmjs.com/package/acme-ghost',
        'package_type' => PackageType::Npm,
    ]);

    Http::fake(['registry.npmjs.org/*' => Http::response('', 404)]);

    purge($project->id);

    expect($project->fresh()->npm_url)->toBeNull();
});

it('keeps both links when each registry rate-limits the check (429) — no half purge', function (): void {
    $project = Project::query()->create([
        'slug' => 'both-links',
        'name' => 'Both Links',
        'category' => ProjectCategory::LaravelPackage,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/acme/lib',
        'packagist_url' => 'https://packagist.org/packages/acme/lib',
        'npm_url' => 'https://www.npmjs.com/package/acme-lib',
    ]);

    Http::fake([
        'packagist.org/*' => Http::response('', 429),
        'registry.npmjs.org/*' => Http::response('', 429),
    ]);

    purge($project->id);

    $fresh = $project->fresh();
    expect($fresh->packagist_url)->toBe('https://packagist.org/packages/acme/lib')
        ->and($fresh->npm_url)->toBe('https://www.npmjs.com/package/acme-lib');
});

it('does not purge when an unrelated link is unknown — retries instead of half purging', function (): void {
    // packagist is clearly foreign, but npm is unknown (429) → release the whole
    // job so packagist is re-checked together with npm next time.
    $project = Project::query()->create([
        'slug' => 'mixed',
        'name' => 'Mixed',
        'category' => ProjectCategory::LaravelPackage,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/acme/lib',
        'packagist_url' => 'https://packagist.org/packages/laravel/laravel',
        'npm_url' => 'https://www.npmjs.com/package/acme-lib',
    ]);

    Http::fake([
        'packagist.org/*' => Http::response(['package' => ['repository' => 'https://github.com/laravel/laravel']], 200),
        'registry.npmjs.org/*' => Http::response('', 429),
    ]);

    purge($project->id);

    // release() is a no-op without a queue instance, so nothing is touched.
    $fresh = $project->fresh();
    expect($fresh->packagist_url)->toBe('https://packagist.org/packages/laravel/laravel')
        ->and($fresh->npm_url)->toBe('https://www.npmjs.com/package/acme-lib');
});

it('keeps links the repo genuinely owns', function (): void {
    $project = Project::query()->create([
        'slug' => 'real',
        'name' => 'Real',
        'category' => ProjectCategory::LaravelPackage,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/jeffersongoncalves/filament-gtag',
        'packagist_url' => 'https://packagist.org/packages/jeffersongoncalves/filament-gtag',
        'package_type' => PackageType::Composer,
        'downloads' => 4200,
        'downloads_label' => '4.2k',
    ]);

    Http::fake(['packagist.org/*' => Http::response(['package' => ['repository' => 'https://github.com/jeffersongoncalves/filament-gtag']], 200)]);

    purge($project->id);

    $fresh = $project->fresh();
    expect($fresh->packagist_url)->toBe('https://packagist.org/packages/jeffersongoncalves/filament-gtag')
        ->and($fresh->package_type)->toBe(PackageType::Composer)
        ->and($fresh->downloads)->toBe(4200);
});

it('does nothing when the project no longer exists', function (): void {
    purge(999999);
})->throwsNoExceptions();

it('does nothing when the project has no github_url', function (): void {
    $project = Project::query()->create([
        'slug' => 'no-github',
        'name' => 'No Github',
        'category' => ProjectCategory::Website,
        'status' => ProjectStatus::Published,
        'packagist_url' => 'https://packagist.org/packages/acme/lib',
    ]);

    purge($project->id);

    expect($project->fresh()->packagist_url)->toBe('https://packagist.org/packages/acme/lib');
});

it('does nothing when the project has neither a packagist nor an npm link', function (): void {
    $project = Project::query()->create([
        'slug' => 'no-links',
        'name' => 'No Links',
        'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published,
        'github_url' => 'https://github.com/acme/tool',
    ]);

    purge($project->id);

    expect($project->fresh()->github_url)->toBe('https://github.com/acme/tool');
});

it('bounds retries by 2 hours and guards with overlap/rate-limit middleware', function (): void {
    $job = new PurgeMisattributedPackageLinksJob(1);

    expect($job->retryUntil())->toBeGreaterThan(now()->addMinutes(119));

    $middleware = $job->middleware();
    expect($middleware)->toHaveCount(2)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[1])->toBeInstanceOf(RateLimited::class);
});

it('logs context when PurgeMisattributedPackageLinksJob fails', function (): void {
    Log::shouldReceive('error')
        ->once()
        ->with('PurgeMisattributedPackageLinksJob failed', Mockery::on(fn ($ctx) => $ctx['project_id'] === 42 && $ctx['error'] === 'boom'));

    (new PurgeMisattributedPackageLinksJob(42))->failed(new RuntimeException('boom'));
});

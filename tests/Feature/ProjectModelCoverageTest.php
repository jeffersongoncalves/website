<?php

declare(strict_types=1);

use App\Enums\PackageType;
use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;

it('builds the monorepo-subtree npm slug from the npm package name, not the github owner/repo', function () {
    $project = new Project([
        'github_url' => 'https://github.com/laravel/framework/tree/12.x/src/Illuminate/Precognition',
        'package_type' => PackageType::Npm,
        'npm_url' => 'https://www.npmjs.com/package/@laravel/precognition-vue',
    ]);

    expect($project->buildVendorRepoSlug())->toBe('laravel-precognition-vue');
});

it('falls back to the raw name when neither a github nor an npm pattern matches', function () {
    $project = new Project(['name' => 'Some Website', 'category' => ProjectCategory::Website]);

    expect($project->buildVendorRepoSlug())->toBe('Some Website');
});

it('scopeAuthored returns no rows when no GitHub username is configured', function () {
    config(['services.github.username' => null]);

    createProject([
        'slug' => 'x', 'name' => 'X', 'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published, 'github_owner' => 'someone',
    ]);

    expect(Project::query()->authored()->count())->toBe(0);
});

it('scopeAuthored matches the configured GitHub username case-insensitively', function () {
    config(['services.github.username' => 'JeffersonGoncalves']);

    // github_owner is denormalised by ProjectObserver::saving() from
    // github_url — not mass-assignable — so set it via a real github_url.
    $mine = createProject([
        'slug' => 'mine', 'name' => 'Mine', 'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published, 'github_url' => 'https://github.com/jeffersongoncalves/x',
    ]);
    createProject([
        'slug' => 'not-mine', 'name' => 'Not Mine', 'category' => ProjectCategory::Tool,
        'status' => ProjectStatus::Published, 'github_url' => 'https://github.com/someone-else/y',
    ]);

    expect($mine->fresh()->github_owner)->toBe('jeffersongoncalves')
        ->and(Project::query()->authored()->pluck('slug')->all())->toBe(['mine']);
});

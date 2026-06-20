<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Models\Project;
use App\Support\ProjectMatcher;

/**
 * Seed a Project with the required `category` filled in.
 *
 * @param  array<string, mixed>  $attributes
 */
function projectMatcher_make(array $attributes): Project
{
    return createProject(array_merge([
        'category' => ProjectCategory::Tool,
    ], $attributes));
}

// ---------------------------------------------------------------------------
// findExisting — github source
// ---------------------------------------------------------------------------

it('matches a github import by github_url', function () {
    $project = projectMatcher_make([
        'slug' => 'repo-a',
        'name' => 'Repo A',
        'github_url' => 'https://github.com/acme/repo-a',
    ]);

    $found = ProjectMatcher::findExisting('github', [
        'github_url' => 'https://github.com/acme/repo-a',
    ]);

    expect($found?->id)->toBe($project->id);
});

it('falls back to docs_url for a github import when github_url does not match', function () {
    $project = projectMatcher_make([
        'slug' => 'docs-seed',
        'name' => 'Docs Seed',
        'docs_url' => 'https://acme.dev',
    ]);

    $found = ProjectMatcher::findExisting('github', [
        'github_url' => 'https://github.com/acme/unknown-repo',
        'docs_url' => 'https://acme.dev',
    ]);

    expect($found?->id)->toBe($project->id);
});

it('returns null for a github import with no matching url', function () {
    projectMatcher_make([
        'slug' => 'other',
        'name' => 'Other',
        'github_url' => 'https://github.com/acme/other',
    ]);

    $found = ProjectMatcher::findExisting('github', [
        'github_url' => 'https://github.com/acme/missing',
        'docs_url' => 'https://nowhere.example',
    ]);

    expect($found)->toBeNull();
});

it('returns null for a github import with no urls at all', function () {
    expect(ProjectMatcher::findExisting('github', []))->toBeNull();
});

// ---------------------------------------------------------------------------
// findExisting — npm source
// ---------------------------------------------------------------------------

it('matches an npm import by npm_url', function () {
    $project = projectMatcher_make([
        'slug' => 'pkg-a',
        'name' => 'Pkg A',
        'npm_url' => 'https://www.npmjs.com/package/pkg-a',
    ]);

    $found = ProjectMatcher::findExisting('npm', [
        'npm_url' => 'https://www.npmjs.com/package/pkg-a',
    ]);

    expect($found?->id)->toBe($project->id);
});

it('falls back to a root github repo for an npm import', function () {
    $project = projectMatcher_make([
        'slug' => 'repo-b',
        'name' => 'Repo B',
        'github_url' => 'https://github.com/acme/repo-b',
    ]);

    $found = ProjectMatcher::findExisting('npm', [
        'npm_url' => 'https://www.npmjs.com/package/unmatched',
        'github_url' => 'https://github.com/acme/repo-b',
    ]);

    expect($found?->id)->toBe($project->id);
});

it('does not collapse an npm monorepo subtree onto the repo root', function () {
    projectMatcher_make([
        'slug' => 'monorepo',
        'name' => 'Monorepo',
        'github_url' => 'https://github.com/acme/monorepo',
    ]);

    $found = ProjectMatcher::findExisting('npm', [
        'github_url' => 'https://github.com/acme/monorepo/tree/main/packages/sub',
    ]);

    expect($found)->toBeNull();
});

it('falls back to docs_url for an npm import when npm and github miss', function () {
    $project = projectMatcher_make([
        'slug' => 'npm-docs',
        'name' => 'Npm Docs',
        'docs_url' => 'https://pkg.example',
    ]);

    $found = ProjectMatcher::findExisting('npm', [
        'npm_url' => 'https://www.npmjs.com/package/nope',
        'docs_url' => 'https://pkg.example',
    ]);

    expect($found?->id)->toBe($project->id);
});

// ---------------------------------------------------------------------------
// findExisting — youtube/url (default branch)
// ---------------------------------------------------------------------------

it('matches a generic url import by docs_url', function () {
    $project = projectMatcher_make([
        'slug' => 'site',
        'name' => 'Site',
        'docs_url' => 'https://example.com',
    ]);

    $found = ProjectMatcher::findExisting('url', [
        'docs_url' => 'https://example.com',
    ]);

    expect($found?->id)->toBe($project->id);
});

it('returns null for a url import with no docs_url', function () {
    expect(ProjectMatcher::findExisting('youtube', []))->toBeNull();
});

// ---------------------------------------------------------------------------
// findByDocsUrl — trailing-slash variants
// ---------------------------------------------------------------------------

it('returns null when docs_url is null', function () {
    expect(ProjectMatcher::findByDocsUrl(null))->toBeNull();
});

it('matches docs_url ignoring a trailing slash mismatch', function () {
    $project = projectMatcher_make([
        'slug' => 'slashy',
        'name' => 'Slashy',
        'docs_url' => 'https://example.com/',
    ]);

    // Stored with trailing slash, queried without it.
    expect(ProjectMatcher::findByDocsUrl('https://example.com')?->id)->toBe($project->id);
});

// ---------------------------------------------------------------------------
// findByGithubUrl — slug extraction + authoritative strcasecmp
// ---------------------------------------------------------------------------

it('returns null from findByGithubUrl when the url has no repo slug', function () {
    expect(ProjectMatcher::findByGithubUrl(null))->toBeNull();
    expect(ProjectMatcher::findByGithubUrl('https://example.com/not-github'))->toBeNull();
});

it('matches a github url case-insensitively', function () {
    $project = projectMatcher_make([
        'slug' => 'casing',
        'name' => 'Casing',
        'github_url' => 'https://github.com/Acme/Repo-Case',
    ]);

    expect(ProjectMatcher::findByGithubUrl('https://github.com/acme/repo-case')?->id)
        ->toBe($project->id);
});

it('rejects a LIKE-substring collision via the strcasecmp authority check', function () {
    // owner/repo "acme/repo" appears as a substring of the stored
    // "acme/repository" url, so the LIKE pre-filter matches but the
    // strcasecmp on the parsed owner/repo must reject it.
    projectMatcher_make([
        'slug' => 'repository',
        'name' => 'Repository',
        'github_url' => 'https://github.com/acme/repository',
    ]);

    expect(ProjectMatcher::findByGithubUrl('https://github.com/acme/repo'))->toBeNull();
});

// ---------------------------------------------------------------------------
// fillMissing — fill empty, skip populated, skip slug
// ---------------------------------------------------------------------------

it('fills only empty attributes and reports the changed keys', function () {
    $project = projectMatcher_make([
        'slug' => 'fill-me',
        'name' => 'Existing Name',
        'license' => '',
        'readme_branch' => null,
    ]);

    $changes = ProjectMatcher::fillMissing($project, [
        'slug' => 'should-be-ignored',
        'name' => 'New Name',
        'license' => 'MIT',
        'readme_branch' => 'main',
    ]);

    expect($changes)->toEqualCanonicalizing(['license', 'readme_branch'])
        ->and($project->name)->toBe('Existing Name')
        ->and($project->license)->toBe('MIT')
        ->and($project->readme_branch)->toBe('main')
        ->and($project->slug)->toBe('fill-me');
});

it('treats null, empty string and empty array as fillable', function () {
    $project = projectMatcher_make([
        'slug' => 'empties',
        'name' => 'Empties',
        'license' => '',
        'readme_branch' => null,
        'topics' => [],
    ]);

    $changes = ProjectMatcher::fillMissing($project, [
        'license' => 'MIT',
        'readme_branch' => 'main',
        'topics' => ['a', 'b'],
    ]);

    expect($changes)->toEqualCanonicalizing(['license', 'readme_branch', 'topics']);
});

it('returns no changes when nothing is empty', function () {
    $project = projectMatcher_make([
        'slug' => 'full',
        'name' => 'Full',
        'license' => 'Apache-2.0',
    ]);

    expect(ProjectMatcher::fillMissing($project, ['license' => 'MIT']))->toBe([]);
});

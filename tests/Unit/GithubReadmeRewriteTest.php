<?php

use App\Support\GithubReadme;

it('maps a real branch back to its user-facing version via overrides', function () {
    expect(GithubReadme::branchToVersion('main', ['v3', 'v4', 'v5'], ['1.x' => 'main']))->toBe('v3');
    expect(GithubReadme::branchToVersion('2.x',  ['v3', 'v4', 'v5'], ['1.x' => 'main']))->toBe('v4');
    expect(GithubReadme::branchToVersion('3.x',  ['v3', 'v4', 'v5'], ['1.x' => 'main']))->toBe('v5');
});

it('maps the auto-branch name when no override matches', function () {
    expect(GithubReadme::branchToVersion('1.x', ['v3', 'v4', 'v5']))->toBe('v3');
    expect(GithubReadme::branchToVersion('2.x', ['v3', 'v4', 'v5']))->toBe('v4');
});

it('returns null for branches that are not tracked', function () {
    expect(GithubReadme::branchToVersion('feature/foo', ['v3', 'v4']))->toBeNull();
    expect(GithubReadme::branchToVersion('99.x', ['v3', 'v4']))->toBeNull();
});

it('rewrites self-repo /tree/{branch} into a local projects.show URL', function () {
    $html = '<p>See <a href="https://github.com/joaopaulolndev/filament-edit-profile/tree/2.x">v4</a> and '
        .'<a href="https://github.com/joaopaulolndev/filament-edit-profile/tree/main">v3</a>.</p>';

    $rewritten = GithubReadme::rewriteSelfRepoLinks(
        $html,
        'https://github.com/joaopaulolndev/filament-edit-profile',
        'filament-edit-profile',
        ['v3', 'v4', 'v5'],
        ['1.x' => 'main'],
    );

    expect($rewritten)
        ->toContain('href="'.route('projects.show', ['slug' => 'filament-edit-profile', 'v' => 'v4']).'"')
        ->toContain('href="'.route('projects.show', ['slug' => 'filament-edit-profile', 'v' => 'v3']).'"');
});

it('strips deep paths after the branch when rewriting', function () {
    $html = '<a href="https://github.com/owner/myrepo/tree/2.x/docs/install.md">docs</a>';

    $rewritten = GithubReadme::rewriteSelfRepoLinks(
        $html,
        'https://github.com/owner/myrepo',
        'myrepo',
        ['v3', 'v4'],
    );

    expect($rewritten)->toContain('href="'.route('projects.show', ['slug' => 'myrepo', 'v' => 'v4']).'"');
});

it('leaves links pointing to a different repo untouched', function () {
    $html = '<a href="https://github.com/other/another/tree/main">x</a>';

    $rewritten = GithubReadme::rewriteSelfRepoLinks(
        $html,
        'https://github.com/owner/myrepo',
        'myrepo',
        ['v3', 'v4'],
    );

    expect($rewritten)->toBe($html);
});

it('leaves /blob /compare /issues self-repo links untouched', function () {
    $html = '<a href="https://github.com/owner/myrepo/blob/main/README.md">readme</a> '
        .'<a href="https://github.com/owner/myrepo/compare/v1...v2">compare</a> '
        .'<a href="https://github.com/owner/myrepo/issues/42">issue</a>';

    $rewritten = GithubReadme::rewriteSelfRepoLinks(
        $html,
        'https://github.com/owner/myrepo',
        'myrepo',
        ['v3', 'v4'],
    );

    expect($rewritten)->toBe($html);
});

it('leaves the html untouched when the project has no tracked versions', function () {
    $html = '<a href="https://github.com/owner/myrepo/tree/main">x</a>';

    $rewritten = GithubReadme::rewriteSelfRepoLinks(
        $html,
        'https://github.com/owner/myrepo',
        'myrepo',
        [],
    );

    expect($rewritten)->toBe($html);
});

it('leaves untracked branches on self-repo links alone', function () {
    $html = '<a href="https://github.com/owner/myrepo/tree/feature/foo">x</a>';

    $rewritten = GithubReadme::rewriteSelfRepoLinks(
        $html,
        'https://github.com/owner/myrepo',
        'myrepo',
        ['v3', 'v4'],
    );

    expect($rewritten)->toBe($html);
});

it('decorates off-site http(s) anchors with target=_blank and rel=nofollow noopener', function () {
    $html = '<a href="https://github.com/owner/repo">github</a> '
        .'<a href="http://example.com">example</a>';

    $out = GithubReadme::markExternalLinks($html, 'jeffersongoncalves.dev.br');

    expect($out)
        ->toContain('target="_blank"')
        ->toContain('rel="nofollow noopener"')
        ->toContain('href="https://github.com/owner/repo"')
        ->toContain('href="http://example.com"');
});

it('does not decorate links pointing to the self host', function () {
    $html = '<a href="https://jeffersongoncalves.dev.br/projects/foo?v=v4">local</a>';

    $out = GithubReadme::markExternalLinks($html, 'jeffersongoncalves.dev.br');

    expect($out)
        ->not->toContain('target="_blank"')
        ->not->toContain('rel="nofollow noopener"');
});

it('does not decorate relative URLs, mailto, or hash anchors', function () {
    $html = '<a href="/projects/foo">local</a> '
        .'<a href="#section">anchor</a> '
        .'<a href="mailto:a@b.com">mail</a>';

    $out = GithubReadme::markExternalLinks($html, 'jeffersongoncalves.dev.br');

    expect($out)->toBe($html);
});

it('preserves existing target and rel attributes without duplicating them', function () {
    $html = '<a target="_self" rel="noopener" href="https://example.com">x</a>';

    $out = GithubReadme::markExternalLinks($html, 'jeffersongoncalves.dev.br');

    expect($out)
        ->toBe($html);
});

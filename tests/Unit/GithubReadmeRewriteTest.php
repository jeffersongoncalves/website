<?php

declare(strict_types=1);

use App\Support\GithubReadme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JeffersonGoncalves\LaravelShortUrl\Models\ShortUrl;

uses(RefreshDatabase::class);

it('maps a real branch back to its user-facing version via overrides', function () {
    expect(GithubReadme::branchToVersion('main', ['v3', 'v4', 'v5'], ['1.x' => 'main']))->toBe('v3');
    expect(GithubReadme::branchToVersion('2.x', ['v3', 'v4', 'v5'], ['1.x' => 'main']))->toBe('v4');
    expect(GithubReadme::branchToVersion('3.x', ['v3', 'v4', 'v5'], ['1.x' => 'main']))->toBe('v5');
});

it('maps the auto-branch name when no override matches', function () {
    expect(GithubReadme::branchToVersion('1.x', ['v3', 'v4', 'v5']))->toBe('v3');
    expect(GithubReadme::branchToVersion('2.x', ['v3', 'v4', 'v5']))->toBe('v4');
});

it('returns null for branches that are not tracked', function () {
    expect(GithubReadme::branchToVersion('feature/foo', ['v3', 'v4']))->toBeNull();
    expect(GithubReadme::branchToVersion('99.x', ['v3', 'v4']))->toBeNull();
});

it('resolves the auto-branch by numeric major regardless of storage order', function () {
    // A persisted `versions` array is not guaranteed ascending (import order,
    // admin edits). branchForFilamentVersion() must still put v4 on 2.x even
    // when v4 sits last in the array — the position it happens to occupy is
    // not what determines its branch.
    $scrambled = ['v3', 'v5', 'v4'];

    expect(GithubReadme::branchForFilamentVersion('v3', $scrambled))->toBe('1.x')
        ->and(GithubReadme::branchForFilamentVersion('v4', $scrambled))->toBe('2.x')
        ->and(GithubReadme::branchForFilamentVersion('v5', $scrambled))->toBe('3.x');
});

it('sorts and dedupes versions by numeric major', function () {
    expect(GithubReadme::sortedVersions(['v5', 'v3', 'v4', 'v3']))->toBe(['v3', 'v4', 'v5']);
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

it('rewrites relative anchor hrefs into absolute GitHub blob URLs', function () {
    $html = '<p><a href="LICENSE">license</a> · <a href="./CONTRIBUTING.md">contrib</a> · '
        .'<a href="docs/install.md">docs</a></p>';

    $out = GithubReadme::rewriteRelativeLinks($html, 'owner/myrepo', '2.x');

    expect($out)
        ->toContain('href="https://github.com/owner/myrepo/blob/2.x/LICENSE"')
        ->toContain('href="https://github.com/owner/myrepo/blob/2.x/CONTRIBUTING.md"')
        ->toContain('href="https://github.com/owner/myrepo/blob/2.x/docs/install.md"');
});

it('leaves absolute, mailto, hash and tel links alone when rewriting relative links', function () {
    $html = '<a href="https://example.com">abs</a> '
        .'<a href="//cdn.example.com/x">proto</a> '
        .'<a href="mailto:a@b.com">mail</a> '
        .'<a href="tel:+5511">tel</a> '
        .'<a href="#anchor">hash</a>';

    $out = GithubReadme::rewriteRelativeLinks($html, 'owner/myrepo', 'main');

    expect($out)->toBe($html);
});

it('rewrites a root-relative href as repo-root-relative, not domain-root', function () {
    // On GitHub itself, a README link to "/root" resolves against the repo
    // root, not the consuming site's own domain root.
    $html = '<a href="/root">root</a>';

    $out = GithubReadme::rewriteRelativeLinks($html, 'owner/myrepo', 'main');

    expect($out)->toBe('<a href="https://github.com/owner/myrepo/blob/main/root">root</a>');
});

it('falls back to HEAD branch when no ref is supplied for relative link rewriting', function () {
    $html = '<a href="LICENSE">x</a>';

    $out = GithubReadme::rewriteRelativeLinks($html, 'owner/myrepo');

    expect($out)->toContain('href="https://github.com/owner/myrepo/blob/HEAD/LICENSE"');
});

it('rewrites relative markdown image sources into absolute raw URLs', function () {
    $md = '![logo](./asset/logo.png) and ![banner](images/banner.svg)';

    $out = GithubReadme::rewriteRelativeAssets($md, 'owner/myrepo', '2.x');

    expect($out)
        ->toContain('![logo](https://raw.githubusercontent.com/owner/myrepo/2.x/asset/logo.png)')
        ->toContain('![banner](https://raw.githubusercontent.com/owner/myrepo/2.x/images/banner.svg)');
});

it('rewrites relative <img src> in raw HTML and strips ?raw=true', function () {
    $md = '<a href="https://echarts.apache.org/"><img style="vertical-align: top;" '
        .'src="./asset/logo.png?raw=true" alt="logo" height="50px"></a>';

    $out = GithubReadme::rewriteRelativeAssets($md, 'owner/myrepo', 'main');

    expect($out)
        ->toContain('src="https://raw.githubusercontent.com/owner/myrepo/main/asset/logo.png"')
        ->not->toContain('raw=true');
});

it('leaves absolute and data asset sources alone', function () {
    $md = '![a](https://cdn.example.com/x.png) '
        .'<img src="data:image/png;base64,AAAA">';

    $out = GithubReadme::rewriteRelativeAssets($md, 'owner/myrepo', 'main');

    expect($out)->toBe($md);
});

it('rewrites a root-relative asset src as repo-root-relative, not domain-root', function () {
    $md = '<img src="/abs/logo.png">';

    $out = GithubReadme::rewriteRelativeAssets($md, 'owner/myrepo', 'main');

    expect($out)->toBe('<img src="https://raw.githubusercontent.com/owner/myrepo/main/abs/logo.png">');
});

it('keeps the first README image eager and lazy-loads the rest', function () {
    $html = '<img src="https://img.shields.io/badge/a.svg">'
        .'<p>x</p><img src="https://user-images.githubusercontent.com/b.png">';

    $out = GithubReadme::lazyloadImages($html);

    // First image: decoding async, but NOT lazy (protects the LCP candidate).
    expect($out)->toContain('<img src="https://img.shields.io/badge/a.svg" decoding="async">');
    // Second image: lazy + async.
    expect($out)
        ->toContain('src="https://user-images.githubusercontent.com/b.png"')
        ->toContain('loading="lazy"');
});

it('does not duplicate existing loading or decoding attributes on README images', function () {
    $html = '<img src="a.png" loading="eager">'
        .'<img src="b.png" decoding="sync" loading="lazy">';

    $out = GithubReadme::lazyloadImages($html);

    expect(substr_count($out, 'loading='))->toBe(2);
    expect(substr_count($out, 'decoding='))->toBe(2);
    expect($out)->toContain('loading="eager"')->toContain('decoding="sync"');
});

it('wraps each README table in a horizontal-scroll container', function () {
    $html = '<p>intro</p><table><thead><tr><th>A</th></tr></thead>'
        ."<tbody><tr><td>1</td></tr></tbody></table>\n"
        .'<table><tr><td>x</td></tr></table>';

    $out = GithubReadme::wrapTables($html);

    // Both tables wrapped, paragraph untouched, no double-wrapping.
    expect(substr_count($out, '<div class="md-table-scroll">'))->toBe(2)
        ->and($out)->toContain('<div class="md-table-scroll"><table><thead>')
        ->and($out)->toContain('</tbody></table></div>')
        ->and($out)->toContain('<p>intro</p>');
});

it('proxies img src on a GitHub asset host through readme-image.show', function () {
    $html = '<img src="https://raw.githubusercontent.com/owner/repo/main/banner.png">';

    $out = GithubReadme::proxyReadmeImages($html);

    expect($out)->toContain('/readme-image/')
        ->and($out)->not->toContain('raw.githubusercontent.com');
});

it('leaves img src on a non-GitHub host untouched', function () {
    $html = '<img src="https://img.shields.io/badge/a.svg">';

    expect(GithubReadme::proxyReadmeImages($html))->toBe($html);
});

it('resolves every outbound link in one batch pass, minting one row per distinct destination', function () {
    // The same destination linked twice (a common README pattern — a badge
    // and a prose mention of the same project) must still mint only once.
    $html = '<a href="https://github.com/jeffersongoncalves/filakitv5">Fila Kit</a> '
        .'<a href="https://github.com/jeffersongoncalves/filament-ban">Filament Ban</a> '
        .'<a href="https://github.com/jeffersongoncalves/filakitv5">again</a> '
        .'<a href="/local">local</a>';

    $out = GithubReadme::rewriteOutboundLinks($html);

    expect(ShortUrl::query()->count())->toBe(2)
        ->and($out)->toContain('href="/local"') // untouched — not off-site
        ->and($out)->not->toContain('github.com'); // both github links rewritten to short urls
});

it('leaves issue, PR, and internal-file GitHub links as real, untracked links', function () {
    $html = '<a href="https://github.com/jeffersongoncalves/filakitv5/issues/12">issue</a> '
        .'<a href="https://github.com/jeffersongoncalves/filakitv5/pull/34">pr</a> '
        .'<a href="https://github.com/jeffersongoncalves/filakitv5/blob/main/LICENSE">license</a> '
        .'<a href="https://github.com/jeffersongoncalves/filakitv5/tree/main/src">src</a> '
        .'<a href="https://github.com/jeffersongoncalves/filakitv5/raw/main/logo.png">logo</a> '
        .'<a href="https://github.com/jeffersongoncalves/filakitv5">repo root</a>';

    $out = GithubReadme::rewriteOutboundLinks($html);

    expect(ShortUrl::query()->count())->toBe(1) // only the repo root minted
        ->and($out)->toContain('href="https://github.com/jeffersongoncalves/filakitv5/issues/12"')
        ->and($out)->toContain('href="https://github.com/jeffersongoncalves/filakitv5/pull/34"')
        ->and($out)->toContain('href="https://github.com/jeffersongoncalves/filakitv5/blob/main/LICENSE"')
        ->and($out)->toContain('href="https://github.com/jeffersongoncalves/filakitv5/tree/main/src"')
        ->and($out)->toContain('href="https://github.com/jeffersongoncalves/filakitv5/raw/main/logo.png"');
});

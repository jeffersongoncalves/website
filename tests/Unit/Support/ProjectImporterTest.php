<?php

declare(strict_types=1);

use App\Support\ProjectImporter;
use Illuminate\Support\Facades\Http;

describe('siteSlugFromUrl', function (): void {
    it('builds a host-based slug with the site- prefix', function (): void {
        expect(ProjectImporter::siteSlugFromUrl('https://getcomposer.org/'))
            ->toBe('site-getcomposer-org');
    });

    it('strips a leading www subdomain', function (): void {
        expect(ProjectImporter::siteSlugFromUrl('https://www.docker.com/'))
            ->toBe('site-docker-com');
    });

    it('appends the path so different paths on the same host do not collide', function (): void {
        expect(ProjectImporter::siteSlugFromUrl('https://laravel.com/'))
            ->toBe('site-laravel-com');

        expect(ProjectImporter::siteSlugFromUrl('https://laravel.com/docs/master/homestead'))
            ->toBe('site-laravel-com-docs-master-homestead');

        expect(ProjectImporter::siteSlugFromUrl('https://laravel.com/'))
            ->not->toBe(ProjectImporter::siteSlugFromUrl('https://laravel.com/docs/master/homestead'));
    });

    it('keeps subdomain segments as part of the host slug', function (): void {
        expect(ProjectImporter::siteSlugFromUrl('https://vapor.laravel.com/'))
            ->toBe('site-vapor-laravel-com');
    });

    it('slugifies the path through Str::slug so it survives weird characters', function (): void {
        expect(ProjectImporter::siteSlugFromUrl('https://example.com/Foo Bar/Baz?ignored=1'))
            ->toBe('site-example-com-foo-bar-baz');
    });

    it('treats a bare host with no path the same as a host with a single slash', function (): void {
        expect(ProjectImporter::siteSlugFromUrl('https://nette.org'))
            ->toBe('site-nette-org');

        expect(ProjectImporter::siteSlugFromUrl('https://nette.org/'))
            ->toBe('site-nette-org');
    });

    it('returns an empty string for an unparseable url', function (): void {
        expect(ProjectImporter::siteSlugFromUrl('not a url'))
            ->toBe('');
    });
});

describe('articleSlugFromUrl', function (): void {
    it('slugs the last path segment with an article- prefix', function (): void {
        expect(ProjectImporter::articleSlugFromUrl('https://yoeri.me/blog/automate-your-php-security-updates'))
            ->toBe('article-automate-your-php-security-updates');
    });

    it('ignores a trailing slash and query string', function (): void {
        expect(ProjectImporter::articleSlugFromUrl('https://example.com/blog/My Post/?utm=x'))
            ->toBe('article-my-post');
    });

    it('falls back to the host when there is no path', function (): void {
        expect(ProjectImporter::articleSlugFromUrl('https://www.example.com'))
            ->toBe('article-example-com');
    });
});

describe('fromArticle', function (): void {
    it('maps a blog post to the article category, naming the row after the post title', function (): void {
        Http::fake([
            '*' => Http::response(
                '<html><head><title>ignored</title>'
                .'<meta property="og:title" content="Automate your PHP security updates">'
                .'<meta property="og:description" content="A short guide to keeping deps patched.">'
                .'<meta property="og:image" content="https://yoeri.me/og/automate.png">'
                .'</head><body></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = ProjectImporter::fromArticle('https://yoeri.me/blog/automate-your-php-security-updates');
        $fields = $result['fields'];

        expect($fields['category'])->toBe('article')
            ->and($fields['name'])->toBe('Automate your PHP security updates')
            ->and($fields['title.en'])->toBe('A short guide to keeping deps patched.')
            ->and($fields['docs_url'])->toBe('https://yoeri.me/blog/automate-your-php-security-updates')
            ->and($fields['slug'])->toBe('article-automate-your-php-security-updates')
            ->and($fields['github_url'])->toBeNull()
            ->and($fields['package_type'])->toBe('none')
            ->and($fields['social_image'])->toBe('https://yoeri.me/og/automate.png');
    });

    it('rejects a non-http url', function (): void {
        expect(ProjectImporter::fromArticle('not a url'))->toBe(['error' => 'invalid_url']);
    });

    it('returns fetch_failed on a non-2xx response', function (): void {
        Http::fake(['*' => Http::response('', 500)]);

        expect(ProjectImporter::fromArticle('https://example.com/blog/down'))
            ->toBe(['error' => 'fetch_failed']);
    });
});

describe('packagistUrlOwnershipStatus', function (): void {
    $url = 'https://packagist.org/packages/vendor/pkg';
    $repo = 'https://github.com/vendor/pkg';

    it('is owned when the Packagist repository matches the repo', function () use ($url, $repo): void {
        Http::fake(['packagist.org/*' => Http::response(['package' => ['repository' => 'https://github.com/vendor/pkg']], 200)]);

        expect(ProjectImporter::packagistUrlOwnershipStatus($url, $repo))
            ->toBe(ProjectImporter::LINK_OWNED);
    });

    it('is foreign when the repository belongs to a different owner', function () use ($url, $repo): void {
        Http::fake(['packagist.org/*' => Http::response(['package' => ['repository' => 'https://github.com/laravel/laravel']], 200)]);

        expect(ProjectImporter::packagistUrlOwnershipStatus($url, $repo))
            ->toBe(ProjectImporter::LINK_FOREIGN);
    });

    it('is owned when only the owner matches — monorepo split-repo (filament)', function (): void {
        // filament/filament package lists its repository as filamentphp/panels,
        // a split read-only repo under the same filamentphp org.
        Http::fake(['packagist.org/*' => Http::response(['package' => ['repository' => 'https://github.com/filamentphp/panels']], 200)]);

        expect(ProjectImporter::packagistUrlOwnershipStatus('https://packagist.org/packages/filament/filament', 'https://github.com/filamentphp/filament'))
            ->toBe(ProjectImporter::LINK_OWNED);
    });

    it('matches the owner even when the repository url carries a .git suffix', function (): void {
        Http::fake(['packagist.org/*' => Http::response(['package' => ['repository' => 'https://github.com/filamentphp/filament.git']], 200)]);

        expect(ProjectImporter::packagistUrlOwnershipStatus('https://packagist.org/packages/filament/filament', 'https://github.com/filamentphp/filament'))
            ->toBe(ProjectImporter::LINK_OWNED);
    });

    it('is foreign when the package is unpublished (404)', function () use ($url, $repo): void {
        Http::fake(['packagist.org/*' => Http::response('', 404)]);

        expect(ProjectImporter::packagistUrlOwnershipStatus($url, $repo))
            ->toBe(ProjectImporter::LINK_FOREIGN);
    });

    it('is unknown on a rate limit (429) so valid links are never purged', function () use ($url, $repo): void {
        Http::fake(['packagist.org/*' => Http::response('', 429)]);

        expect(ProjectImporter::packagistUrlOwnershipStatus($url, $repo))
            ->toBe(ProjectImporter::LINK_UNKNOWN);
    });

    it('is unknown on a server error (5xx)', function () use ($url, $repo): void {
        Http::fake(['packagist.org/*' => Http::response('', 503)]);

        expect(ProjectImporter::packagistUrlOwnershipStatus($url, $repo))
            ->toBe(ProjectImporter::LINK_UNKNOWN);
    });

    it('is unknown when the response has no repository', function () use ($url, $repo): void {
        Http::fake(['packagist.org/*' => Http::response(['package' => ['downloads' => ['total' => 0]]], 200)]);

        expect(ProjectImporter::packagistUrlOwnershipStatus($url, $repo))
            ->toBe(ProjectImporter::LINK_UNKNOWN);
    });

    it('is unknown for a malformed packagist url (never purge what we cannot parse)', function () use ($repo): void {
        expect(ProjectImporter::packagistUrlOwnershipStatus('https://packagist.org/explore', $repo))
            ->toBe(ProjectImporter::LINK_UNKNOWN);
    });
});

describe('npmUrlOwnershipStatus', function (): void {
    $url = 'https://www.npmjs.com/package/mautic';
    $repo = 'https://github.com/mautic/mautic';

    it('is owned when the registry repository matches the repo', function (): void {
        Http::fake(['registry.npmjs.org/*' => Http::response(['repository' => ['url' => 'https://github.com/jeffersongoncalves/foo']], 200)]);

        expect(ProjectImporter::npmUrlOwnershipStatus('https://www.npmjs.com/package/foo', 'https://github.com/jeffersongoncalves/foo'))
            ->toBe(ProjectImporter::LINK_OWNED);
    });

    it('is foreign when the registry repository points elsewhere (mautic)', function () use ($url, $repo): void {
        Http::fake(['registry.npmjs.org/*' => Http::response(['repository' => ['url' => 'https://github.com/someone/else']], 200)]);

        expect(ProjectImporter::npmUrlOwnershipStatus($url, $repo))
            ->toBe(ProjectImporter::LINK_FOREIGN);
    });

    it('is foreign when the package is unpublished (404)', function () use ($url, $repo): void {
        Http::fake(['registry.npmjs.org/*' => Http::response('', 404)]);

        expect(ProjectImporter::npmUrlOwnershipStatus($url, $repo))
            ->toBe(ProjectImporter::LINK_FOREIGN);
    });

    it('is unknown on a rate limit (429)', function () use ($url, $repo): void {
        Http::fake(['registry.npmjs.org/*' => Http::response('', 429)]);

        expect(ProjectImporter::npmUrlOwnershipStatus($url, $repo))
            ->toBe(ProjectImporter::LINK_UNKNOWN);
    });

    it('handles a scoped package name', function (): void {
        Http::fake(['registry.npmjs.org/*' => Http::response(['repository' => ['url' => 'https://github.com/acme/pkg']], 200)]);

        expect(ProjectImporter::npmUrlOwnershipStatus('https://www.npmjs.com/package/@acme/pkg', 'https://github.com/acme/pkg'))
            ->toBe(ProjectImporter::LINK_OWNED);
    });
});

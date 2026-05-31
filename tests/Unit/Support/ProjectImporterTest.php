<?php

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
            ->and($fields['package_type'])->toBe('none');
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

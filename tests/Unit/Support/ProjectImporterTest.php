<?php

use App\Support\ProjectImporter;

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

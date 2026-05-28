<?php

use App\Support\ProjectAttributes;

describe('prettifyName', function (): void {
    it('title-cases a hyphenated repo slug', function (): void {
        expect(ProjectAttributes::prettifyName('filament-cep-field'))
            ->toBe('Filament Cep Field');
    });

    it('replaces underscores with spaces', function (): void {
        expect(ProjectAttributes::prettifyName('laravel_excel_export'))
            ->toBe('Laravel Excel Export');
    });

    it('drops scope @ prefix and treats slash as separator', function (): void {
        expect(ProjectAttributes::prettifyName('@tailwindcss/vite'))
            ->toBe('Tailwindcss Vite');
    });

    it('owner/repo collapses to spaced words', function (): void {
        expect(ProjectAttributes::prettifyName('spatie/laravel-permission'))
            ->toBe('Spatie Laravel Permission');
    });

    it('replaces dots with spaces', function (): void {
        expect(ProjectAttributes::prettifyName('vue.js'))
            ->toBe('Vue Js');
    });

    it('collapses multiple consecutive separators into a single space', function (): void {
        expect(ProjectAttributes::prettifyName('foo--bar__baz..qux'))
            ->toBe('Foo Bar Baz Qux');
    });

    it('trims leading and trailing whitespace introduced by replacements', function (): void {
        expect(ProjectAttributes::prettifyName('/owner/repo/'))
            ->toBe('Owner Repo');
    });

    it('preserves single word input casing-wise', function (): void {
        expect(ProjectAttributes::prettifyName('packagist'))
            ->toBe('Packagist');
    });

    it('returns an empty string when the input is empty', function (): void {
        expect(ProjectAttributes::prettifyName(''))->toBe('');
    });

    it('handles a paper.mary-ui.com style name', function (): void {
        expect(ProjectAttributes::prettifyName('paper.mary-ui.com'))
            ->toBe('Paper Mary Ui Com');
    });

    it('uppercases the standalone Php token to PHP', function (): void {
        expect(ProjectAttributes::prettifyName('awesome-php'))
            ->toBe('Awesome PHP');
    });

    it('uppercases Php at the start of the name', function (): void {
        expect(ProjectAttributes::prettifyName('php-best-practices'))
            ->toBe('PHP Best Practices');
    });

    it('does not touch Php when it is part of a larger word', function (): void {
        expect(ProjectAttributes::prettifyName('phpunit'))
            ->toBe('Phpunit');
    });

    it('uppercases Php between other words', function (): void {
        expect(ProjectAttributes::prettifyName('symfony-php-cheat-sheets'))
            ->toBe('Symfony PHP Cheat Sheets');
    });
});

describe('normalize', function (): void {
    it('returns the same shape when no dotted keys or empties are present', function (): void {
        $input = [
            'name' => 'Filament Cep Field',
            'github_url' => 'https://github.com/jeffersongoncalves/filament-cep-field',
            'stars' => 12,
        ];

        expect(ProjectAttributes::normalize($input))->toBe($input);
    });

    it('drops null, empty-string, and empty-array values', function (): void {
        $input = [
            'name' => 'Keep',
            'description' => null,
            'tag' => '',
            'extras' => [],
            'kept' => 0,
        ];

        expect(ProjectAttributes::normalize($input))
            ->toBe(['name' => 'Keep', 'kept' => 0]);
    });

    it('keeps zero, false, and "0" values because they are not empty', function (): void {
        $input = [
            'stars' => 0,
            'featured' => false,
            'zero_str' => '0',
        ];

        expect(ProjectAttributes::normalize($input))->toBe($input);
    });

    it('expands a dotted translatable key into a nested locale array', function (): void {
        $input = ['title.pt' => 'Olá', 'title.en' => 'Hello'];

        expect(ProjectAttributes::normalize($input))
            ->toBe(['title' => ['pt' => 'Olá', 'en' => 'Hello']]);
    });

    it('merges dotted keys with the same column under one nested array', function (): void {
        $input = [
            'title.en' => 'Hello',
            'title.es' => 'Hola',
            'title.pt' => 'Olá',
        ];

        expect(ProjectAttributes::normalize($input))
            ->toBe(['title' => ['en' => 'Hello', 'es' => 'Hola', 'pt' => 'Olá']]);
    });

    it('keeps plain keys alongside dotted ones', function (): void {
        $input = [
            'name' => 'Foo',
            'title.en' => 'Foo Title',
        ];

        expect(ProjectAttributes::normalize($input))
            ->toBe([
                'name' => 'Foo',
                'title' => ['en' => 'Foo Title'],
            ]);
    });

    it('only splits on the first dot so deeper paths stay in the locale segment', function (): void {
        $input = ['title.pt.BR' => 'Olá BR'];

        expect(ProjectAttributes::normalize($input))
            ->toBe(['title' => ['pt.BR' => 'Olá BR']]);
    });

    it('returns an empty array when given an empty array', function (): void {
        expect(ProjectAttributes::normalize([]))->toBe([]);
    });

    it('drops a dotted key when its value is empty (no translation slot created)', function (): void {
        $input = ['title.pt' => '', 'title.en' => 'Hello'];

        expect(ProjectAttributes::normalize($input))
            ->toBe(['title' => ['en' => 'Hello']]);
    });
});

<?php

use App\Support\GoogleTranslate;
use Illuminate\Support\Facades\Http;

it('translates a simple string by stitching the chunked response together', function (): void {
    Http::fake([
        'translate.googleapis.com/translate_a/single*' => Http::response([
            [
                ['Hello world.', 'Olá mundo.', null, null, 1],
                ['', '', null, null, 0],
            ],
            null,
            'pt',
        ], 200),
    ]);

    $output = GoogleTranslate::translate('Olá mundo.', 'en');

    expect($output)->toBe('Hello world.');
});

it('concatenates multi-chunk responses', function (): void {
    Http::fake([
        'translate.googleapis.com/translate_a/single*' => Http::response([
            [
                ['First sentence. ', 'Primeira frase. ', null, null, 1],
                ['Second sentence.', 'Segunda frase.', null, null, 1],
            ],
            null,
            'pt',
        ], 200),
    ]);

    $output = GoogleTranslate::translate('Primeira frase. Segunda frase.', 'en');

    expect($output)->toBe('First sentence. Second sentence.');
});

it('returns an empty string when the input is empty', function (): void {
    Http::fake(fn () => throw new RuntimeException('should not hit the network'));

    expect(GoogleTranslate::translate('   ', 'en'))->toBe('');
});

it('returns null on a non-2xx response', function (): void {
    Http::fake([
        'translate.googleapis.com/*' => Http::response('rate-limited', 429),
    ]);

    expect(GoogleTranslate::translate('Olá mundo.', 'en'))->toBeNull();
});

it('returns null when the response payload has an unexpected shape', function (): void {
    Http::fake([
        'translate.googleapis.com/*' => Http::response(['not-the-expected-shape' => true], 200),
    ]);

    expect(GoogleTranslate::translate('Olá mundo.', 'en'))->toBeNull();
});

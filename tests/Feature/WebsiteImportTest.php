<?php

declare(strict_types=1);

use App\Support\ProjectImporter;
use Illuminate\Support\Facades\Http;

it('imports a generic website from its head meta', function () {
    Http::fake([
        '*tool.test*' => Http::response(
            '<html><head><title>Cool Tool</title>'
            .'<meta property="og:title" content="Cool Tool"></head></html>',
            200,
        ),
    ]);

    $result = ProjectImporter::fromUrl('https://tool.test');

    expect($result['error'] ?? null)->toBeNull()
        ->and($result['fields']['docs_url'] ?? null)->toBe('https://tool.test')
        ->and($result['fields']['category'] ?? null)->toBe('website')
        ->and($result['fields']['name'] ?? null)->not->toBeNull();
});

it('rejects a non-http url', function () {
    expect(ProjectImporter::fromUrl('not-a-url')['error'] ?? null)->toBe('invalid_url');
    expect(ProjectImporter::fromUrl('ftp://tool.test')['error'] ?? null)->toBe('invalid_url');
});

it('returns fetch_failed when the website responds with a server error', function () {
    Http::fake(['*down.test*' => Http::response('', 500)]);

    expect(ProjectImporter::fromUrl('https://down.test')['error'] ?? null)->toBe('fetch_failed');
});

it('does not cache a failed website import (transient outage is retried)', function () {
    // fetchPageHtml retries with a second User-Agent, so one failed import
    // consumes two responses — both must fail for the call to error out.
    Http::fake([
        '*flaky.test*' => Http::sequence()
            ->push('', 500)
            ->push('', 500)
            ->push('<html><head><title>Back Up</title></head></html>', 200),
    ]);

    expect(ProjectImporter::fromUrl('https://flaky.test')['error'] ?? null)->toBe('fetch_failed');
    // Second attempt must re-fetch (the error was not cached) and succeed.
    expect(ProjectImporter::fromUrl('https://flaky.test')['error'] ?? null)->toBeNull();
});

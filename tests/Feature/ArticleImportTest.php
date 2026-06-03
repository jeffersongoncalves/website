<?php

use App\Support\ProjectImporter;
use Illuminate\Support\Facades\Http;

it('falls back to a link-preview crawler UA when the first request is blocked', function () {
    // Medium et al. 403 a datacenter browser UA but serve og: meta to known
    // link-preview bots. First call (browser UA) 403s, retry (Slackbot UA) 200s.
    Http::fake([
        'blocked.test/*' => Http::sequence()
            ->push('', 403)
            ->push('<html><head><meta property="og:title" content="Fallback Worked"></head></html>', 200),
    ]);

    $result = ProjectImporter::fromArticle('https://blocked.test/some-post');

    expect($result['fields']['name'] ?? null)->toBe('Fallback Worked');
});

it('returns fetch_failed only when every UA is blocked', function () {
    Http::fake(['blocked.test/*' => Http::response('', 403)]);

    expect(ProjectImporter::fromArticle('https://blocked.test/some-post')['error'] ?? null)
        ->toBe('fetch_failed');
});

it('reads the article publish date from og article:published_time', function () {
    Http::fake([
        'blog.test/*' => Http::response(
            '<html><head><meta property="og:title" content="My Post">'
            .'<meta property="article:published_time" content="2024-03-15T10:00:00Z"></head></html>',
            200,
        ),
    ]);

    $result = ProjectImporter::fromArticle('https://blog.test/my-post');

    expect($result['fields']['published_at'] ?? null)->toContain('2024-03-15')
        ->and($result['warnings'] ?? [])->not->toContain('no_published_date');
});

it('warns and leaves the date blank when the article ships no publish date', function () {
    Http::fake([
        'blog.test/*' => Http::response('<html><head><meta property="og:title" content="No Date"></head></html>', 200),
    ]);

    $result = ProjectImporter::fromArticle('https://blog.test/no-date');

    expect($result['fields']['published_at'] ?? null)->toBeNull()
        ->and($result['warnings'])->toContain('no_published_date');
});

it('ignores an implausible (future) publish date', function () {
    Http::fake([
        'blog.test/*' => Http::response(
            '<html><head><meta property="og:title" content="Future Post">'
            .'<meta property="article:published_time" content="2099-01-01T00:00:00Z"></head></html>',
            200,
        ),
    ]);

    $result = ProjectImporter::fromArticle('https://blog.test/future');

    expect($result['fields']['published_at'] ?? null)->toBeNull()
        ->and($result['warnings'])->toContain('no_published_date');
});

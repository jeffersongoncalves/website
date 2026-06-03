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

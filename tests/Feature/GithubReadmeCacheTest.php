<?php

use App\Models\ReadmeCache;
use App\Support\GithubReadme;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('github'));

it('does not send a conditional etag when the cached file is missing', function () {
    // A ReadmeCache row that survived (etag set) while its on-disk file did not
    // — e.g. a fresh deploy disk with the database carried over. Sending the
    // stale If-None-Match would draw a 304 with no body and no file to fall back
    // on, yielding a permanent null. The fetch must ignore the etag instead.
    ReadmeCache::query()->create([
        'repo' => 'owner/repo',
        'ref' => 'default',
        'etag' => '"stale-etag"',
        'html_path' => 'readme/owner__repo/default.html', // not on disk
        'checked_at' => now()->subHour(),
    ]);

    $html = GithubReadme::fetchHtml('https://github.com/owner/repo');

    // A body is rendered (not the permanent null the bug produced)...
    expect($html)->not->toBeNull();

    // ...because the README request went out without the stale conditional header.
    Http::assertSent(fn ($request) => str_contains($request->url(), '/readme')
        && ! $request->hasHeader('If-None-Match'));
});

it('still sends the conditional etag when the cached file is present', function () {
    // Sanity check the opposite branch: with the file on disk, the etag IS used
    // (so a 304 cheaply reuses it and spares the rate limit).
    Storage::disk('github')->put('readme/owner__repo/default.html', '<p>cached</p>');

    ReadmeCache::query()->create([
        'repo' => 'owner/repo',
        'ref' => 'default',
        'etag' => '"live-etag"',
        'html_path' => 'readme/owner__repo/default.html',
        'checked_at' => now()->subHour(),
    ]);

    GithubReadme::fetchHtml('https://github.com/owner/repo');

    Http::assertSent(fn ($request) => ! str_contains($request->url(), '/readme')
        || $request->hasHeader('If-None-Match'));
});

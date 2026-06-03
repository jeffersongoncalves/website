<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');
uses(RefreshDatabase::class)->in('Feature');

pest()->beforeEach(function () {
    Queue::fake();

    // Start each test with a cold cache — the array store survives
    // RefreshDatabase's rollback, so a value cached in one test (e.g. the
    // SiteStat singleton behind SiteStats::all()) would otherwise leak into the
    // next and read as stale.
    Cache::flush();

    // Full-page response cache off by default so tests assert freshly-rendered
    // output; the dedicated CachePublicPageTest re-enables it explicitly.
    config(['filakit.page_cache_enabled' => false]);

    Http::preventStrayRequests();
    Http::fake([
        'api.github.com/users/*' => Http::response(['followers' => 0, 'public_repos' => 0]),
        'api.github.com/repos/*/readme' => Http::response('# README', 200, ['Content-Type' => 'text/plain']),
        'api.github.com/repos/*/branches*' => Http::response([['name' => 'main'], ['name' => '1.x']]),
        'api.github.com/repos/*/contributors*' => Http::response([['login' => 'jeffersongoncalves', 'contributions' => 0]]),
        'api.github.com/repos/*' => Http::response(['default_branch' => 'main', 'stargazers_count' => 0]),
        'api.github.com/graphql' => Http::response(['data' => [
            'user' => [
                'contributionsCollection' => ['contributionCalendar' => ['totalContributions' => 0, 'weeks' => []]],
                'sponsorshipsAsMaintainer' => ['totalCount' => 0],
            ],
        ]]),
        'raw.githubusercontent.com/*' => Http::response('# README', 200),
        'packagist.org/*' => Http::response(['package' => ['downloads' => ['total' => 0]]]),
        'api.npmjs.org/*' => Http::response(['downloads' => 0]),
        'plugins.jetbrains.com/*' => Http::response(['downloads' => 0]),
    ]);
})->in('Feature');

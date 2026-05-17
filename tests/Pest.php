<?php

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

pest()->beforeEach(function () {
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
        'plugins.jetbrains.com/*' => Http::response(['downloads' => 0]),
    ]);
})->in('Feature', 'Unit');

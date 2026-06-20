<?php

use App\Support\GithubContributions;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

/**
 * Swap in a fresh HTTP factory so the global GitHub Http::fake in tests/Pest.php
 * (Feature suite) can't shadow per-test stubs, then register the given fakes.
 *
 * @param  array<string, mixed>  $stubs
 */
function githubContributions_fakeHttp(array $stubs): void
{
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake($stubs);
}

/**
 * Build a GraphQL contributionCalendar payload from a list of weeks.
 *
 * @param  array<int, array<int, array{weekday: int, level: string}>>  $weeks
 */
function githubContributions_calendar(int $total, array $weeks): array
{
    $payloadWeeks = [];

    foreach ($weeks as $days) {
        $contributionDays = [];

        foreach ($days as $day) {
            $contributionDays[] = [
                'contributionCount' => 1,
                'contributionLevel' => $day['level'],
                'weekday' => $day['weekday'],
            ];
        }

        $payloadWeeks[] = ['contributionDays' => $contributionDays];
    }

    return ['data' => [
        'user' => [
            'contributionsCollection' => [
                'contributionCalendar' => [
                    'totalContributions' => $total,
                    'weeks' => $payloadWeeks,
                ],
            ],
        ],
    ]];
}

it('returns empty fallback when no token is configured', function () {
    config(['services.github.token' => null]);

    // No HTTP should happen — preventStrayRequests guards this.
    Http::swap(new Factory);
    Http::preventStrayRequests();

    expect(GithubContributions::fetch('jeffersongoncalves'))
        ->toBe(['cells' => [], 'total' => 0]);
});

it('parses a successful GraphQL response into column-major cells', function () {
    config(['services.github.token' => 'test-token']);

    githubContributions_fakeHttp([
        'api.github.com/graphql' => Http::response(githubContributions_calendar(5, [
            // Week 1 — only weekday 0 and 2 present; others default to 0.
            [
                ['weekday' => 0, 'level' => 'FIRST_QUARTILE'],
                ['weekday' => 2, 'level' => 'FOURTH_QUARTILE'],
            ],
            // Week 2 — covers every enum level mapping.
            [
                ['weekday' => 0, 'level' => 'NONE'],
                ['weekday' => 1, 'level' => 'FIRST_QUARTILE'],
                ['weekday' => 2, 'level' => 'SECOND_QUARTILE'],
                ['weekday' => 3, 'level' => 'THIRD_QUARTILE'],
                ['weekday' => 4, 'level' => 'FOURTH_QUARTILE'],
                ['weekday' => 5, 'level' => 'UNKNOWN_VALUE'],
                ['weekday' => 6, 'level' => 'NONE'],
            ],
        ])),
    ]);

    $result = GithubContributions::fetch('jeffersongoncalves');

    expect($result['total'])->toBe(5)
        ->and($result['cells'])->toBe([
            // Week 1: weekday 0..6
            1, 0, 4, 0, 0, 0, 0,
            // Week 2: weekday 0..6 (UNKNOWN_VALUE → 0)
            0, 1, 2, 3, 4, 0, 0,
        ]);
});

it('sends an authorized GraphQL request to the right endpoint', function () {
    config(['services.github.token' => 'secret-token']);

    githubContributions_fakeHttp([
        'api.github.com/graphql' => Http::response(githubContributions_calendar(0, [])),
    ]);

    GithubContributions::fetch('octocat');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.github.com/graphql'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer secret-token')
            && $request['variables']['login'] === 'octocat';
    });

    Http::assertSentCount(1);
});

it('returns empty fallback when the calendar data is missing', function () {
    config(['services.github.token' => 'test-token']);

    githubContributions_fakeHttp([
        'api.github.com/graphql' => Http::response(['data' => ['user' => null]]),
    ]);

    expect(GithubContributions::fetch('jeffersongoncalves'))
        ->toBe(['cells' => [], 'total' => 0]);
});

it('returns empty fallback on an HTTP error response', function () {
    config(['services.github.token' => 'test-token']);

    githubContributions_fakeHttp([
        'api.github.com/graphql' => Http::response('Server error', 500),
    ]);

    expect(GithubContributions::fetch('jeffersongoncalves'))
        ->toBe(['cells' => [], 'total' => 0]);
});

it('handles a calendar with no weeks gracefully', function () {
    config(['services.github.token' => 'test-token']);

    githubContributions_fakeHttp([
        'api.github.com/graphql' => Http::response(githubContributions_calendar(42, [])),
    ]);

    expect(GithubContributions::fetch('jeffersongoncalves'))
        ->toBe(['cells' => [], 'total' => 42]);
});

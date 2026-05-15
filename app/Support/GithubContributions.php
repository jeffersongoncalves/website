<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

class GithubContributions
{
    /**
     * Fetch the contribution calendar from the GitHub GraphQL API.
     *
     * Returns a flat list of contribution levels (0-4) ordered the same way
     * GitHub renders the calendar: column-major (week-by-week, top→bottom).
     *
     * This is called by the scheduled sync command, not by the request path —
     * the rendered result is persisted on the `site_stats` row and read back
     * via SiteStats::contributions().
     *
     * @return array{cells: list<int>, total: int}
     */
    public static function fetch(string $login): array
    {
        $token = config('services.github.token');

        if (! $token) {
            return ['cells' => [], 'total' => 0];
        }

        $query = <<<'GQL'
        query($login: String!) {
          user(login: $login) {
            contributionsCollection {
              contributionCalendar {
                totalContributions
                weeks {
                  contributionDays {
                    contributionCount
                    contributionLevel
                    weekday
                  }
                }
              }
            }
          }
        }
        GQL;

        $response = Http::timeout(8)
            ->withHeaders([
                'Authorization' => "Bearer {$token}",
                'User-Agent' => 'jeffersongoncalves-site',
            ])
            ->post('https://api.github.com/graphql', [
                'query' => $query,
                'variables' => ['login' => $login],
            ]);

        if (! $response->successful()) {
            return ['cells' => [], 'total' => 0];
        }

        $calendar = $response->json('data.user.contributionsCollection.contributionCalendar');

        if (! $calendar) {
            return ['cells' => [], 'total' => 0];
        }

        $cells = [];

        foreach ($calendar['weeks'] ?? [] as $week) {
            $byDay = array_fill(0, 7, 0);

            foreach ($week['contributionDays'] ?? [] as $day) {
                $byDay[$day['weekday']] = self::levelFromEnum($day['contributionLevel'] ?? 'NONE');
            }

            foreach ($byDay as $level) {
                $cells[] = $level;
            }
        }

        return [
            'cells' => $cells,
            'total' => (int) ($calendar['totalContributions'] ?? 0),
        ];
    }

    private static function levelFromEnum(string $enum): int
    {
        return match ($enum) {
            'FIRST_QUARTILE' => 1,
            'SECOND_QUARTILE' => 2,
            'THIRD_QUARTILE' => 3,
            'FOURTH_QUARTILE' => 4,
            default => 0,
        };
    }
}

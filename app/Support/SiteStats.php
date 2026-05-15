<?php

namespace App\Support;

use App\Enums\ProjectCategory;
use App\Models\Project;
use App\Models\SiteStat;
use Illuminate\Support\Facades\Http;

class SiteStats
{
    private const GITHUB_LOGIN = 'jeffersongoncalves';

    /**
     * Read the persisted site stats. The values are written by the scheduled
     * `projects:sync-metrics` command — the request path never recomputes nor
     * hits an external API. If the row is missing (e.g. before the first sync,
     * or while GitHub is unavailable) a zeroed set is returned so the site
     * still renders; views hide GitHub-dependent pieces when the data is empty.
     *
     * @return array{
     *   repos:int, filament:int, laravel:int, starter:int, tool:int,
     *   stars:int, downloads:int, followers:int, public_sponsors:int,
     *   contributions:array{cells:list<int>,total:int}
     * }
     */
    public static function all(): array
    {
        $stat = SiteStat::query()->first();

        if (! $stat) {
            return self::empty();
        }

        return self::toArray($stat);
    }

    /**
     * Zeroed stats — the safe fallback when nothing has been synced yet.
     *
     * @return array{
     *   repos:int, filament:int, laravel:int, starter:int, tool:int,
     *   stars:int, downloads:int, followers:int, public_sponsors:int,
     *   contributions:array{cells:list<int>,total:int}
     * }
     */
    private static function empty(): array
    {
        return [
            'repos' => 0,
            'filament' => 0,
            'laravel' => 0,
            'starter' => 0,
            'tool' => 0,
            'stars' => 0,
            'downloads' => 0,
            'followers' => 0,
            'public_sponsors' => 0,
            'contributions' => ['cells' => [], 'total' => 0],
        ];
    }

    /**
     * Recompute every stat and upsert the singleton row. Called by the
     * scheduled sync command, not by the request path.
     *
     * @return array{
     *   repos:int, filament:int, laravel:int, starter:int, tool:int,
     *   stars:int, downloads:int, followers:int, public_sponsors:int,
     *   contributions:array{cells:list<int>,total:int}
     * }
     */
    public static function persist(): array
    {
        $data = self::compute();

        $stat = SiteStat::query()->firstOrNew([]);
        $stat->fill($data);
        $stat->synced_at = now();
        $stat->save();

        return self::toArray($stat);
    }

    /**
     * Contribution calendar for the heatmap, read from the persisted row.
     *
     * @return array{cells:list<int>,total:int}
     */
    public static function contributions(): array
    {
        $stat = SiteStat::query()->first();

        if (! $stat) {
            return ['cells' => [], 'total' => 0];
        }

        return $stat->contributions ?? ['cells' => [], 'total' => 0];
    }

    /**
     * Stats formatted for the "countUp" Alpine component (numbers + suffix + label key).
     *
     * @return list<array{label_key:string,target:float|int,suffix?:string,decimals?:int}>
     */
    public static function homeCards(): array
    {
        $s = self::all();

        return [
            ['label_key' => 'os.repos',     'target' => $s['repos']],
            ['label_key' => 'os.followers', 'target' => self::scaleK($s['followers']), 'suffix' => self::suffixK($s['followers']), 'decimals' => 1],
            ['label_key' => 'os.downloads', 'target' => self::scaleM($s['downloads']), 'suffix' => self::suffixM($s['downloads']), 'decimals' => 1],
            ['label_key' => 'os.plugins',   'target' => $s['filament'], 'suffix' => '+'],
        ];
    }

    /**
     * @return list<array{label_key:string,target:float|int,suffix?:string,decimals?:int}>
     */
    public static function osCards(): array
    {
        $s = self::all();

        return [
            ['label_key' => 'os.repos',            'target' => $s['repos']],
            ['label_key' => 'os.followers',        'target' => self::scaleK($s['followers']), 'suffix' => self::suffixK($s['followers']), 'decimals' => 1],
            ['label_key' => 'os.downloads',        'target' => self::scaleM($s['downloads']), 'suffix' => self::suffixM($s['downloads']), 'decimals' => 1],
            ['label_key' => 'os.plugins_filament', 'target' => $s['filament']],
            ['label_key' => 'os.packages_laravel', 'target' => $s['laravel']],
            ['label_key' => 'os.starter_kits',     'target' => $s['starter']],
            ['label_key' => 'os.stars',            'target' => self::scaleK($s['stars']), 'suffix' => self::suffixK($s['stars']), 'decimals' => 1],
            ['label_key' => 'os.public_sponsors',  'target' => $s['public_sponsors']],
        ];
    }

    /**
     * @return array{
     *   repos:int, filament:int, laravel:int, starter:int, tool:int,
     *   stars:int, downloads:int, followers:int, public_sponsors:int,
     *   contributions:array{cells:list<int>,total:int}
     * }
     */
    private static function toArray(SiteStat $stat): array
    {
        return [
            'repos' => $stat->repos,
            'filament' => $stat->filament,
            'laravel' => $stat->laravel,
            'starter' => $stat->starter,
            'tool' => $stat->tool,
            'stars' => $stat->stars,
            'downloads' => $stat->downloads,
            'followers' => $stat->followers,
            'public_sponsors' => $stat->public_sponsors,
            'contributions' => $stat->contributions ?? ['cells' => [], 'total' => 0],
        ];
    }

    /**
     * @return array{
     *   repos:int, filament:int, laravel:int, starter:int, tool:int,
     *   stars:int, downloads:int, followers:int, public_sponsors:int,
     *   contributions:array{cells:list<int>,total:int}
     * }
     */
    private static function compute(): array
    {
        $base = Project::query()->published();

        $stars = (int) (clone $base)->sum('stars');
        $downloads = (int) (clone $base)->sum('downloads');

        $github = self::fetchGithubUser(self::GITHUB_LOGIN);

        return [
            'repos' => (int) (clone $base)->count(),
            'filament' => (int) (clone $base)->byCategory(ProjectCategory::FilamentPlugin)->count(),
            'laravel' => (int) (clone $base)->byCategory(ProjectCategory::LaravelPackage)->count(),
            'starter' => (int) (clone $base)->byCategory(ProjectCategory::StarterKit)->count(),
            'tool' => (int) (clone $base)->byCategory(ProjectCategory::Tool)->count(),
            'stars' => $stars,
            'downloads' => $downloads,
            'followers' => $github['followers'] ?? 0,
            'public_sponsors' => self::fetchSponsorCount(self::GITHUB_LOGIN),
            'contributions' => GithubContributions::fetch(self::GITHUB_LOGIN),
        ];
    }

    /**
     * @return array{followers?:int,public_repos?:int}
     */
    private static function fetchGithubUser(string $login): array
    {
        $headers = ['User-Agent' => 'jeffersongoncalves-site', 'Accept' => 'application/vnd.github+json'];

        if ($token = config('services.github.token')) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        $response = Http::timeout(8)->withHeaders($headers)->get("https://api.github.com/users/{$login}");

        if (! $response->successful()) {
            return [];
        }

        return [
            'followers' => (int) ($response->json('followers') ?? 0),
            'public_repos' => (int) ($response->json('public_repos') ?? 0),
        ];
    }

    private static function fetchSponsorCount(string $login): int
    {
        $token = config('services.github.token');

        if (! $token) {
            return 0;
        }

        $query = <<<'GQL'
        query($login: String!) {
          user(login: $login) {
            sponsorshipsAsMaintainer(first: 100, activeOnly: true, includePrivate: false) {
              totalCount
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
            return 0;
        }

        return (int) ($response->json('data.user.sponsorshipsAsMaintainer.totalCount') ?? 0);
    }

    private static function scaleK(int $n): float|int
    {
        return $n >= 1000 ? round($n / 1000, 1) : $n;
    }

    private static function suffixK(int $n): string
    {
        return $n >= 1000 ? 'k' : '';
    }

    private static function scaleM(int $n): float|int
    {
        if ($n >= 1_000_000) {
            return round($n / 1_000_000, 1);
        }
        if ($n >= 1_000) {
            return round($n / 1_000, 1);
        }

        return $n;
    }

    private static function suffixM(int $n): string
    {
        if ($n >= 1_000_000) {
            return 'M';
        }
        if ($n >= 1_000) {
            return 'k';
        }

        return '';
    }
}

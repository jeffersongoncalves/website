<?php

namespace App\Support;

use App\Enums\ProjectCategory;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SiteStats
{
    private const CACHE_KEY = 'site.stats';
    private const TTL_HOURS = 6;

    /**
     * @return array{
     *   repos:int, filament:int, laravel:int, starter:int, tool:int,
     *   stars:int, downloads:int, followers:int, public_sponsors:int
     * }
     */
    public static function all(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHours(self::TTL_HOURS), fn () => self::compute());
    }

    public static function refresh(): array
    {
        Cache::forget(self::CACHE_KEY);

        return self::all();
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

    private static function compute(): array
    {
        $base = Project::query()->published();

        $stars     = (int) (clone $base)->sum('stars');
        $downloads = (int) (clone $base)->sum('downloads');

        $github = self::fetchGithubUser('jeffersongoncalves');

        return [
            'repos'           => (int) (clone $base)->count(),
            'filament'        => (int) (clone $base)->byCategory(ProjectCategory::FilamentPlugin)->count(),
            'laravel'         => (int) (clone $base)->byCategory(ProjectCategory::LaravelPackage)->count(),
            'starter'         => (int) (clone $base)->byCategory(ProjectCategory::StarterKit)->count(),
            'tool'            => (int) (clone $base)->byCategory(ProjectCategory::Tool)->count(),
            'stars'           => $stars,
            'downloads'       => $downloads,
            'followers'       => $github['followers'] ?? 0,
            'public_sponsors' => self::fetchSponsorCount('jeffersongoncalves'),
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
            'followers'    => (int) ($response->json('followers') ?? 0),
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
                'User-Agent'    => 'jeffersongoncalves-site',
            ])
            ->post('https://api.github.com/graphql', [
                'query'     => $query,
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

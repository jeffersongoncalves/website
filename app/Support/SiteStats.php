<?php

namespace App\Support;

use App\Enums\PackageType;
use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\SiteStat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
     * The assembled array is cached forever and invalidated by SiteStat's
     * saved/deleted hook, so the several widgets + Livewire components that read
     * it (some more than once per render) share a single query per write cycle
     * instead of one query per call.
     *
     * @return array{
     *   repos:int, catalogue:int, filament:int, laravel:int, livewire:int, cakephp:int, laravel_zero:int,
     *   ide_plugin:int, framework:int, starter:int, saas:int, tool:int, docker:int, database:int, website:int, youtube_channel:int,
     *   php_package:int, javascript_package:int, css_framework:int, application:int, learning_resource:int, awesome_list:int, mobile_library:int,
     *   maintained:int, daily_drivers:int,
     *   stars:int, downloads:int, downloads_packagist:int, downloads_npm:int, downloads_jetbrains:int, downloads_docker:int,
     *   followers:int, public_sponsors:int,
     *   contributions:array{cells:list<int>,total:int},
     *   languages:list<array{language:string,total:int}>,
     *   topics:list<array{topic:string,total:int}>
     * }
     */
    public static function all(): array
    {
        return Cache::rememberForever(SiteStat::CACHE_KEY, function (): array {
            $stat = SiteStat::query()->first();

            return $stat ? self::toArray($stat) : self::empty();
        });
    }

    /**
     * Zeroed stats — the safe fallback when nothing has been synced yet.
     *
     * @return array{
     *   repos:int, catalogue:int, filament:int, laravel:int, livewire:int, cakephp:int, laravel_zero:int,
     *   ide_plugin:int, framework:int, starter:int, saas:int, tool:int, docker:int, database:int, website:int, youtube_channel:int,
     *   php_package:int, javascript_package:int, css_framework:int, application:int, learning_resource:int, awesome_list:int, mobile_library:int,
     *   maintained:int, daily_drivers:int,
     *   stars:int, downloads:int, downloads_packagist:int, downloads_npm:int, downloads_jetbrains:int, downloads_docker:int,
     *   followers:int, public_sponsors:int,
     *   contributions:array{cells:list<int>,total:int},
     *   languages:list<array{language:string,total:int}>,
     *   topics:list<array{topic:string,total:int}>
     * }
     */
    private static function empty(): array
    {
        return [
            'repos' => 0,
            'catalogue' => 0,
            'filament' => 0,
            'laravel' => 0,
            'livewire' => 0,
            'cakephp' => 0,
            'laravel_zero' => 0,
            'ide_plugin' => 0,
            'framework' => 0,
            'starter' => 0,
            'saas' => 0,
            'tool' => 0,
            'docker' => 0,
            'database' => 0,
            'website' => 0,
            'youtube_channel' => 0,
            'php_package' => 0,
            'javascript_package' => 0,
            'css_framework' => 0,
            'application' => 0,
            'learning_resource' => 0,
            'awesome_list' => 0,
            'mobile_library' => 0,
            'maintained' => 0,
            'daily_drivers' => 0,
            'stars' => 0,
            'downloads' => 0,
            'downloads_packagist' => 0,
            'downloads_npm' => 0,
            'downloads_jetbrains' => 0,
            'downloads_docker' => 0,
            'followers' => 0,
            'public_sponsors' => 0,
            'contributions' => ['cells' => [], 'total' => 0],
            'languages' => [],
            'topics' => [],
        ];
    }

    /**
     * Recompute only the columns derived from the local Project table
     * (counts, category breakdowns, stars/downloads sums). GitHub-sourced
     * fields (followers, public_sponsors, contributions) are left intact
     * so admin edits don't trigger external API calls. Called from the
     * Project observer so the SiteMetricsWidget reflects new projects on
     * the next page load instead of waiting for the scheduled sync.
     */
    public static function refreshProjectDerived(): void
    {
        $base = Project::query()->published();

        $data = [
            // "Repos" is the headline count of repositories the user actually
            // owns on GitHub. The full curated catalogue (which mixes in
            // thousands of third-party projects) is tracked separately as
            // `catalogue` so the public-repos number stays honest.
            'repos' => self::ownedReposCount(),
            'catalogue' => (int) (clone $base)->count(),
            'filament' => (int) (clone $base)->byCategory(ProjectCategory::FilamentPlugin)->count(),
            'laravel' => (int) (clone $base)->byCategory(ProjectCategory::LaravelPackage)->count(),
            'livewire' => (int) (clone $base)->byCategory(ProjectCategory::LivewirePackage)->count(),
            'cakephp' => (int) (clone $base)->byCategory(ProjectCategory::CakePhpPackage)->count(),
            'laravel_zero' => (int) (clone $base)->byCategory(ProjectCategory::LaravelZeroCli)->count(),
            'ide_plugin' => (int) (clone $base)->byCategory(ProjectCategory::IdePlugin)->count(),
            'framework' => (int) (clone $base)->byCategory(ProjectCategory::Framework)->count(),
            'starter' => (int) (clone $base)->byCategory(ProjectCategory::StarterKit)->count(),
            'saas' => (int) (clone $base)->byCategory(ProjectCategory::Saas)->count(),
            'tool' => (int) (clone $base)->byCategory(ProjectCategory::Tool)->count(),
            'docker' => (int) (clone $base)->byCategory(ProjectCategory::Docker)->count(),
            'database' => (int) (clone $base)->byCategory(ProjectCategory::Database)->count(),
            'website' => (int) (clone $base)->byCategory(ProjectCategory::Website)->count(),
            'youtube_channel' => (int) (clone $base)->byCategory(ProjectCategory::YoutubeChannel)->count(),
            'php_package' => (int) (clone $base)->byCategory(ProjectCategory::PhpPackage)->count(),
            'javascript_package' => (int) (clone $base)->byCategory(ProjectCategory::JavascriptPackage)->count(),
            'css_framework' => (int) (clone $base)->byCategory(ProjectCategory::CssFramework)->count(),
            'application' => (int) (clone $base)->byCategory(ProjectCategory::Application)->count(),
            'learning_resource' => (int) (clone $base)->byCategory(ProjectCategory::LearningResource)->count(),
            'awesome_list' => (int) (clone $base)->byCategory(ProjectCategory::AwesomeList)->count(),
            'mobile_library' => (int) (clone $base)->byCategory(ProjectCategory::MobileLibrary)->count(),
            'maintained' => (int) (clone $base)->maintained()->count(),
            'daily_drivers' => (int) (clone $base)->where('is_daily_driver', true)->count(),
            'stars' => (int) (clone $base)->sum('stars'),
            'downloads' => (int) (clone $base)->sum('downloads'),
            'downloads_packagist' => (int) (clone $base)->where('package_type', PackageType::Composer->value)->sum('downloads'),
            'downloads_npm' => (int) (clone $base)->where('package_type', PackageType::Npm->value)->sum('downloads'),
            'downloads_jetbrains' => (int) (clone $base)->where('package_type', PackageType::JetBrains->value)->sum('downloads'),
            'downloads_docker' => (int) (clone $base)->where('package_type', PackageType::Docker->value)->sum('downloads'),
            'languages' => self::languageBreakdown(),
            'topics' => self::topicBreakdown(),
        ];

        $stat = SiteStat::query()->firstOrNew([]);
        $stat->fill($data);

        if (! $stat->exists) {
            $stat->synced_at = now();
        }

        $stat->save();
    }

    /**
     * Recompute every stat and upsert the singleton row. Called by the
     * scheduled sync command, not by the request path.
     *
     * @return array{
     *   repos:int, catalogue:int, filament:int, laravel:int, livewire:int, cakephp:int, laravel_zero:int,
     *   ide_plugin:int, framework:int, starter:int, saas:int, tool:int, docker:int, database:int, website:int, youtube_channel:int,
     *   php_package:int, javascript_package:int, css_framework:int, application:int, learning_resource:int, awesome_list:int, mobile_library:int,
     *   maintained:int, daily_drivers:int,
     *   stars:int, downloads:int, downloads_packagist:int, downloads_npm:int, downloads_jetbrains:int, downloads_docker:int,
     *   followers:int, public_sponsors:int,
     *   contributions:array{cells:list<int>,total:int},
     *   languages:list<array{language:string,total:int}>,
     *   topics:list<array{topic:string,total:int}>
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
        // Ride the same cached array as all() rather than firing a second query.
        return self::all()['contributions'];
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
            ['label_key' => 'os.repos',                'target' => $s['repos']],
            ['label_key' => 'os.catalogue',            'target' => $s['catalogue']],
            ['label_key' => 'os.followers',            'target' => self::scaleK($s['followers']), 'suffix' => self::suffixK($s['followers']), 'decimals' => 1],
            ['label_key' => 'os.downloads_packagist',  'target' => self::scaleM($s['downloads_packagist']), 'suffix' => self::suffixM($s['downloads_packagist']), 'decimals' => 1],
            ['label_key' => 'os.downloads_npm',        'target' => self::scaleM($s['downloads_npm']), 'suffix' => self::suffixM($s['downloads_npm']), 'decimals' => 1],
            ['label_key' => 'os.downloads_jetbrains',  'target' => self::scaleM($s['downloads_jetbrains']), 'suffix' => self::suffixM($s['downloads_jetbrains']), 'decimals' => 1],
            ['label_key' => 'os.plugins_filament',     'target' => $s['filament']],
            ['label_key' => 'os.packages_laravel',     'target' => $s['laravel']],
            ['label_key' => 'os.starter_kits',         'target' => $s['starter']],
            ['label_key' => 'os.stars',                'target' => self::scaleK($s['stars']), 'suffix' => self::suffixK($s['stars']), 'decimals' => 1],
            ['label_key' => 'os.public_sponsors',      'target' => $s['public_sponsors']],
        ];
    }

    /**
     * @return array{
     *   repos:int, catalogue:int, filament:int, laravel:int, livewire:int, cakephp:int, laravel_zero:int,
     *   ide_plugin:int, framework:int, starter:int, saas:int, tool:int, docker:int, database:int, website:int, youtube_channel:int,
     *   php_package:int, javascript_package:int, css_framework:int, application:int, learning_resource:int, awesome_list:int, mobile_library:int,
     *   maintained:int, daily_drivers:int,
     *   stars:int, downloads:int, downloads_packagist:int, downloads_npm:int, downloads_jetbrains:int, downloads_docker:int,
     *   followers:int, public_sponsors:int,
     *   contributions:array{cells:list<int>,total:int},
     *   languages:list<array{language:string,total:int}>,
     *   topics:list<array{topic:string,total:int}>
     * }
     */
    private static function toArray(SiteStat $stat): array
    {
        return [
            'repos' => $stat->repos,
            'catalogue' => $stat->catalogue,
            'filament' => $stat->filament,
            'laravel' => $stat->laravel,
            'livewire' => $stat->livewire,
            'cakephp' => $stat->cakephp,
            'laravel_zero' => $stat->laravel_zero,
            'ide_plugin' => $stat->ide_plugin,
            'framework' => $stat->framework,
            'starter' => $stat->starter,
            'saas' => $stat->saas,
            'tool' => $stat->tool,
            'docker' => $stat->docker,
            'database' => $stat->database,
            'website' => $stat->website,
            'youtube_channel' => $stat->youtube_channel,
            'php_package' => $stat->php_package,
            'javascript_package' => $stat->javascript_package,
            'css_framework' => $stat->css_framework,
            'application' => $stat->application,
            'learning_resource' => $stat->learning_resource,
            'awesome_list' => $stat->awesome_list,
            'mobile_library' => $stat->mobile_library,
            'maintained' => $stat->maintained,
            'daily_drivers' => $stat->daily_drivers,
            'stars' => $stat->stars,
            'downloads' => $stat->downloads,
            'downloads_packagist' => $stat->downloads_packagist,
            'downloads_npm' => $stat->downloads_npm,
            'downloads_jetbrains' => $stat->downloads_jetbrains,
            'downloads_docker' => $stat->downloads_docker,
            'followers' => $stat->followers,
            'public_sponsors' => $stat->public_sponsors,
            'contributions' => $stat->contributions ?? ['cells' => [], 'total' => 0],
            'languages' => $stat->languages ?? [],
            'topics' => $stat->topics ?? [],
        ];
    }

    /**
     * @return array{
     *   repos:int, catalogue:int, filament:int, laravel:int, livewire:int, cakephp:int, laravel_zero:int,
     *   ide_plugin:int, framework:int, starter:int, saas:int, tool:int, docker:int, database:int, website:int, youtube_channel:int,
     *   php_package:int, javascript_package:int, css_framework:int, application:int, learning_resource:int, awesome_list:int, mobile_library:int,
     *   maintained:int, daily_drivers:int,
     *   stars:int, downloads:int, downloads_packagist:int, downloads_npm:int, downloads_jetbrains:int, downloads_docker:int,
     *   followers:int, public_sponsors:int,
     *   contributions:array{cells:list<int>,total:int},
     *   languages:list<array{language:string,total:int}>,
     *   topics:list<array{topic:string,total:int}>
     * }
     */
    private static function compute(): array
    {
        $base = Project::query()->published();

        $stars = (int) (clone $base)->sum('stars');
        $downloads = (int) (clone $base)->sum('downloads');
        $downloadsPackagist = (int) (clone $base)->where('package_type', PackageType::Composer->value)->sum('downloads');
        $downloadsNpm = (int) (clone $base)->where('package_type', PackageType::Npm->value)->sum('downloads');
        $downloadsJetbrains = (int) (clone $base)->where('package_type', PackageType::JetBrains->value)->sum('downloads');
        $downloadsDocker = (int) (clone $base)->where('package_type', PackageType::Docker->value)->sum('downloads');

        $github = self::fetchGithubUser(self::GITHUB_LOGIN);

        return [
            'repos' => self::ownedReposCount(),
            'catalogue' => (int) (clone $base)->count(),
            'filament' => (int) (clone $base)->byCategory(ProjectCategory::FilamentPlugin)->count(),
            'laravel' => (int) (clone $base)->byCategory(ProjectCategory::LaravelPackage)->count(),
            'livewire' => (int) (clone $base)->byCategory(ProjectCategory::LivewirePackage)->count(),
            'cakephp' => (int) (clone $base)->byCategory(ProjectCategory::CakePhpPackage)->count(),
            'laravel_zero' => (int) (clone $base)->byCategory(ProjectCategory::LaravelZeroCli)->count(),
            'ide_plugin' => (int) (clone $base)->byCategory(ProjectCategory::IdePlugin)->count(),
            'framework' => (int) (clone $base)->byCategory(ProjectCategory::Framework)->count(),
            'starter' => (int) (clone $base)->byCategory(ProjectCategory::StarterKit)->count(),
            'saas' => (int) (clone $base)->byCategory(ProjectCategory::Saas)->count(),
            'tool' => (int) (clone $base)->byCategory(ProjectCategory::Tool)->count(),
            'docker' => (int) (clone $base)->byCategory(ProjectCategory::Docker)->count(),
            'database' => (int) (clone $base)->byCategory(ProjectCategory::Database)->count(),
            'website' => (int) (clone $base)->byCategory(ProjectCategory::Website)->count(),
            'youtube_channel' => (int) (clone $base)->byCategory(ProjectCategory::YoutubeChannel)->count(),
            'php_package' => (int) (clone $base)->byCategory(ProjectCategory::PhpPackage)->count(),
            'javascript_package' => (int) (clone $base)->byCategory(ProjectCategory::JavascriptPackage)->count(),
            'css_framework' => (int) (clone $base)->byCategory(ProjectCategory::CssFramework)->count(),
            'application' => (int) (clone $base)->byCategory(ProjectCategory::Application)->count(),
            'learning_resource' => (int) (clone $base)->byCategory(ProjectCategory::LearningResource)->count(),
            'awesome_list' => (int) (clone $base)->byCategory(ProjectCategory::AwesomeList)->count(),
            'mobile_library' => (int) (clone $base)->byCategory(ProjectCategory::MobileLibrary)->count(),
            'maintained' => (int) (clone $base)->maintained()->count(),
            'daily_drivers' => (int) (clone $base)->where('is_daily_driver', true)->count(),
            'stars' => $stars,
            'downloads' => $downloads,
            'downloads_packagist' => $downloadsPackagist,
            'downloads_npm' => $downloadsNpm,
            'downloads_jetbrains' => $downloadsJetbrains,
            'downloads_docker' => $downloadsDocker,
            'followers' => $github['followers'] ?? 0,
            'public_sponsors' => self::fetchSponsorCount(self::GITHUB_LOGIN),
            'contributions' => GithubContributions::fetch(self::GITHUB_LOGIN),
            'languages' => self::languageBreakdown(),
            'topics' => self::topicBreakdown(),
        ];
    }

    /**
     * Published-project counts per topic, busiest first (top 30). Aggregated in
     * PHP so it stays portable across DB engines (no JSON-array SQL functions).
     * Stored on the SiteStat row — the request path never recomputes.
     *
     * @return list<array{topic:string,total:int}>
     */
    private static function topicBreakdown(): array
    {
        $counts = [];

        DB::table('projects')
            ->where('status', ProjectStatus::Published->value)
            ->whereNotNull('topics')
            ->select('topics')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$counts): void {
                foreach ($rows as $row) {
                    $topics = json_decode((string) $row->topics, true);

                    if (! is_array($topics)) {
                        continue;
                    }

                    foreach ($topics as $topic) {
                        if (is_string($topic) && $topic !== '') {
                            $counts[$topic] = ($counts[$topic] ?? 0) + 1;
                        }
                    }
                }
            });

        arsort($counts);

        $out = [];
        foreach (array_slice($counts, 0, 30, true) as $topic => $total) {
            $out[] = ['topic' => $topic, 'total' => $total];
        }

        return $out;
    }

    /**
     * Published-project counts per language, busiest first — drives the
     * admin language metrics widget. Stored on the SiteStat row so the request
     * path never recomputes.
     *
     * @return list<array{language:string,total:int}>
     */
    private static function languageBreakdown(): array
    {
        // DB::table (not Eloquent) so `language` comes back as the raw string,
        // not the ProjectLanguage enum cast.
        return DB::table('projects')
            ->where('status', ProjectStatus::Published->value)
            ->whereNotNull('language')
            ->where('language', '!=', '')
            ->selectRaw('language, count(*) as total')
            ->groupBy('language')
            ->orderByDesc('total')
            ->orderBy('language')
            ->get()
            ->map(fn ($row): array => [
                'language' => (string) $row->language,
                'total' => (int) $row->total,
            ])
            ->all();
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

    /**
     * Count of published projects whose GitHub repo is owned by the site's
     * own login — the honest "public repositories" headline, distinct from
     * the curated catalogue total.
     */
    private static function ownedReposCount(): int
    {
        return (int) Project::query()->published()
            ->whereRaw('lower(github_url) like ?', ['%github.com/'.self::GITHUB_LOGIN.'/%'])
            ->count();
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

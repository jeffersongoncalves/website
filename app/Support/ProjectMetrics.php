<?php

namespace App\Support;

use App\Enums\PackageType;
use App\Enums\ProjectCategory;
use App\Enums\ProjectLanguage;
use App\Exceptions\GithubRateLimitException;
use App\Models\Project;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProjectMetrics
{
    /**
     * Shared HTTP client for outbound metrics calls: 3 attempts with a 200ms
     * backoff, retrying only on connection-level failures (timeouts/DNS),
     * plus an explicit connect-vs-total timeout split. 4xx/5xx responses are
     * not retried — callers never call ->throw(), so no RequestException is
     * raised and each caller inspects $response->successful() itself. A final
     * exhausted connection failure throws ConnectionException, which the
     * dispatching job already catches.
     */
    private static function http(): PendingRequest
    {
        // throw: false keeps a 4xx/5xx as a returned Response instead of raising
        // RequestException — callers inspect $response->successful() themselves
        // (and throwIfRateLimited() turns a 403 rate-limit into a typed signal).
        // Connection-level failures still throw and are what the 3× retry covers.
        return Http::retry(3, 200, throw: false)->connectTimeout(4)->timeout(8);
    }

    /**
     * Abort the whole sync the moment GitHub signals a rate limit, rather than
     * letting every remaining call 403 in turn and spam the log. Covers both
     * the primary limit (403 + `X-RateLimit-Remaining: 0`) and the secondary /
     * abuse limit (403/429 carrying a `Retry-After`). The thrown exception is
     * caught by SyncProjectMetricsJob, which releases back to the queue with a
     * delay until the window resets.
     *
     * @throws GithubRateLimitException
     */
    private static function throwIfRateLimited(Response $response): void
    {
        $status = $response->status();

        if ($status !== 403 && $status !== 429) {
            return;
        }

        $retryAfterHeader = $response->header('Retry-After');
        $remaining = $response->header('X-RateLimit-Remaining');

        if ($remaining !== '0' && $retryAfterHeader === '') {
            return;
        }

        if ($retryAfterHeader !== '') {
            $retryAfter = (int) $retryAfterHeader;
        } else {
            $retryAfter = ((int) $response->header('X-RateLimit-Reset')) - time();
        }

        throw new GithubRateLimitException(max(60, $retryAfter));
    }

    public static function sync(Project $project): bool
    {
        $changed = false;

        // Fetch the repo snapshot first: it already carries `default_branch`,
        // so the URL-resolvers and branch repair below reuse it instead of each
        // re-issuing their own GET /repos/{repo} (3 redundant calls/project).
        $snapshot = self::fetchRepoSnapshot($project->github_url);
        $defaultBranch = $snapshot['default_branch'] ?? null;

        $resolvedPackagistUrl = self::resolvePackagistUrl($project, $defaultBranch);
        if ($resolvedPackagistUrl !== null && $resolvedPackagistUrl !== $project->packagist_url) {
            $project->packagist_url = $resolvedPackagistUrl;
            $changed = true;
        }

        $resolvedNpmUrl = self::resolveNpmUrl($project, $defaultBranch);
        if ($resolvedNpmUrl !== null && $resolvedNpmUrl !== $project->npm_url) {
            $project->npm_url = $resolvedNpmUrl;
            $changed = true;
        }

        if ($snapshot !== null) {
            if ($snapshot['stars'] !== $project->stars) {
                $project->stars = $snapshot['stars'];
                $changed = true;
            }

            // Capture the repo's primary language for free from the same
            // /repos call — lets the catalogue facet generic applications by
            // language instead of leaving them in one undifferentiated bucket.
            // Unknown languages (not in the enum) are ignored.
            $language = $snapshot['language'] !== null
                ? ProjectLanguage::tryFrom($snapshot['language'])
                : null;
            if ($language !== null && $language !== $project->language) {
                $project->language = $language;
                $changed = true;
            }

            // Re-classify generic application/tool rows as the repo's topics
            // evolve — a project later tagged `awesome`/`android`/`database`/etc
            // gets promoted out of the catch-all on the next sync. Only upgrades
            // these two buckets; never downgrades a curated category.
            if (in_array($project->category, [ProjectCategory::Application, ProjectCategory::Tool], true)) {
                $upgraded = ProjectClassifier::specificFromTopics($snapshot['topics'], (string) $project->repo);

                if ($upgraded !== null && $upgraded !== $project->category->value) {
                    $project->category = ProjectCategory::from($upgraded);
                    $changed = true;
                }
            }
        }

        // Merge topics from every source we can reach: GitHub topics (curated)
        // and Packagist keywords. Only overwrites when something was found so a
        // source-less refresh doesn't wipe import-seeded topics.
        $rawTopics = $snapshot !== null ? $snapshot['topics'] : [];
        if ($project->packagist_url) {
            $rawTopics = array_merge($rawTopics, self::fetchPackagistKeywords($project->packagist_url));
        }
        $topics = ProjectTopics::normalize($rawTopics);
        if ($topics !== [] && $topics !== ($project->topics ?? [])) {
            $project->topics = $topics;
            $changed = true;
        }

        $downloads = self::fetchDownloads($project);
        if ($downloads !== null && $downloads !== $project->downloads) {
            $project->downloads = $downloads;
            $project->downloads_label = self::formatDownloads($downloads);
            $changed = true;
        }

        $repairedOverrides = self::repairBranchOverrides($project, $defaultBranch);
        if ($repairedOverrides !== null) {
            $project->branch_overrides = $repairedOverrides;
            $changed = true;
        }

        $contributions = self::fetchUserContributions($project->github_url);
        if ($contributions !== null && $contributions !== $project->user_contributions) {
            $project->user_contributions = $contributions;
            $changed = true;
        }

        // Auto-demote a maintainer flag to daily-driver when the user has
        // zero verified commits on the repo's default branch. Avoids leaving
        // stale "maintainer" badges on projects the user does not actually
        // contribute to.
        if ($project->is_maintainer && (int) $project->user_contributions === 0 && $contributions !== null) {
            $project->is_maintainer = false;
            $project->is_daily_driver = true;
            $changed = true;
        }

        if ($changed) {
            $project->last_synced_at = now();
            $project->save();
        }

        return $changed;
    }

    /**
     * Verify each branch_overrides entry against the real branch list on
     * GitHub. Manual overrides that still point at an existing branch are
     * preserved verbatim. Only entries pointing at a now-missing branch are
     * repaired — first by trying the auto-branch (1.x, 2.x, ...), then
     * the repo's default branch (typically `main` or `master`).
     *
     * @return array<string,string>|null the repaired map, or null when no
     *                                   change is needed / when verification
     *                                   cannot be performed (network error,
     *                                   no GitHub URL, non-Filament project)
     */
    private static function repairBranchOverrides(Project $project, ?string $defaultBranch): ?array
    {
        if (! is_array($project->versions) || $project->versions === []) {
            return null;
        }

        $branches = self::fetchBranches($project->github_url);

        if ($branches === null) {
            return null;
        }

        $current = is_array($project->branch_overrides) ? $project->branch_overrides : [];
        $versions = array_values($project->versions);
        $lastIndex = count($versions) - 1;

        $next = [];
        foreach ($versions as $i => $version) {
            $autoBranch = ($i + 1).'.x';
            $currentValue = isset($current[$autoBranch]) ? trim((string) $current[$autoBranch]) : '';

            // Manual override still pointing at a real branch — keep verbatim.
            if ($currentValue !== '' && in_array($currentValue, $branches, true)) {
                $next[$autoBranch] = $currentValue;

                continue;
            }

            // Build a prioritized candidate list per version.
            $candidates = [];

            // 1. Literal Filament major number ("v3" → "3.x"). Filament core
            //    repos + many plugins follow this convention.
            if (preg_match('/^v(\d+)$/', $version, $m)) {
                $candidates[] = $m[1].'.x';
            }

            // 2. Auto-branch ("1.x", "2.x", "3.x") — older plugins started here.
            $candidates[] = $autoBranch;

            // 3. Default branch (main/master) as last resort — covers plugins
            //    whose oldest tracked version was developed directly on the
            //    default branch (e.g. v3 on main for many community plugins).
            //    Filament core repos hit their {major}.x first so this never
            //    accidentally pulls v5 content for an earlier version.
            if ($defaultBranch !== null) {
                $candidates[] = $defaultBranch;
            }

            $resolved = null;
            foreach ($candidates as $candidate) {
                if (in_array($candidate, $branches, true)) {
                    $resolved = $candidate;

                    break;
                }
            }

            $next[$autoBranch] = $resolved ?? ($currentValue !== '' ? $currentValue : $autoBranch);
        }

        return $next === $current ? null : $next;
    }

    /**
     * @return list<string>|null branch names, or null when the GitHub API
     *                           request cannot be completed.
     */
    private static function fetchBranches(?string $githubUrl): ?array
    {
        $repo = GithubReadme::repoFromUrl($githubUrl);

        if (! $repo) {
            return null;
        }

        $headers = ['User-Agent' => 'jeffersongoncalves-site', 'Accept' => 'application/vnd.github+json'];

        if ($token = config('services.github.token')) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        // Single page of 100 covers every real case: the version branches we
        // resolve against are a handful (1.x..5.x, main, master, develop). The
        // old 5-page loop spent up to 4 extra calls/project chasing branches we
        // never match — a meaningful slice of the GitHub rate-limit budget.
        $response = self::http()
            ->withHeaders($headers)
            ->get("https://api.github.com/repos/{$repo}/branches", [
                'per_page' => 100,
            ]);

        self::throwIfRateLimited($response);

        if (! $response->successful()) {
            Log::warning('GitHub branches API failed', ['repo' => $repo, 'status' => $response->status()]);

            return null;
        }

        $names = [];
        foreach ((array) $response->json() as $branch) {
            if (is_array($branch) && isset($branch['name']) && is_string($branch['name'])) {
                $names[] = $branch['name'];
            }
        }

        return $names;
    }

    /**
     * Count the configured GitHub user's commits to a repo via the
     * /repos/{repo}/contributors endpoint. Returns null when verification
     * cannot be performed (no URL, no username, network error).
     */
    private static function fetchUserContributions(?string $githubUrl): ?int
    {
        $repo = GithubReadme::repoFromUrl($githubUrl);
        $username = strtolower((string) config('services.github.username'));

        if (! $repo || $username === '') {
            return null;
        }

        $headers = ['User-Agent' => 'jeffersongoncalves-site', 'Accept' => 'application/vnd.github+json'];

        if ($token = config('services.github.token')) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        // Contributors come ordered by commit count desc, so the top 100 holds
        // anyone with a meaningful contribution. The old 5-page walk burned up
        // to 4 extra calls/project on popular repos hunting a user who, if
        // absent from page 1, contributed too little to matter (result: 0).
        $response = self::http()
            ->withHeaders($headers)
            ->get("https://api.github.com/repos/{$repo}/contributors", [
                'per_page' => 100,
                'anon' => 'false',
            ]);

        self::throwIfRateLimited($response);

        if (! $response->successful()) {
            Log::warning('GitHub contributors API failed', ['repo' => $repo, 'status' => $response->status()]);

            return null;
        }

        foreach ((array) $response->json() as $contributor) {
            if (! is_array($contributor) || ! isset($contributor['login'])) {
                continue;
            }

            if (strtolower((string) $contributor['login']) === $username) {
                return (int) ($contributor['contributions'] ?? 0);
            }
        }

        return 0;
    }

    /**
     * Fetch stars + primary language + topics + default branch in a single
     * /repos call. Returns null when the request can't be completed; `language`
     * is null for repos GitHub reports no language for (docs-only, empty, etc.).
     * `default_branch` is reused by the URL-resolvers and branch repair so they
     * don't each re-fetch /repos/{repo}.
     *
     * @return array{stars:int, language:?string, topics:list<string>, default_branch:?string}|null
     */
    private static function fetchRepoSnapshot(?string $githubUrl): ?array
    {
        $repo = GithubReadme::repoFromUrl($githubUrl);

        if (! $repo) {
            return null;
        }

        $headers = ['User-Agent' => 'jeffersongoncalves-site', 'Accept' => 'application/vnd.github+json'];

        if ($token = config('services.github.token')) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        $response = self::http()
            ->withHeaders($headers)
            ->get("https://api.github.com/repos/{$repo}");

        self::throwIfRateLimited($response);

        if (! $response->successful()) {
            Log::warning('GitHub API failed', ['repo' => $repo, 'status' => $response->status()]);

            return null;
        }

        $language = $response->json('language');

        $topics = $response->json('topics');
        $topics = is_array($topics)
            ? array_values(array_filter($topics, 'is_string'))
            : [];

        $defaultBranch = $response->json('default_branch');

        return [
            'stars' => (int) ($response->json('stargazers_count') ?? 0),
            'language' => is_string($language) && $language !== '' ? $language : null,
            'topics' => $topics,
            'default_branch' => is_string($defaultBranch) && $defaultBranch !== '' ? $defaultBranch : null,
        ];
    }

    private static function fetchDownloads(Project $project): ?int
    {
        if ($jetBrainsId = self::jetBrainsIdFromUrl($project->docs_url)) {
            return self::fetchJetBrainsDownloads($jetBrainsId);
        }

        if ($project->packagist_url) {
            return self::fetchPackagistDownloads($project->packagist_url);
        }

        if ($project->npm_url) {
            return self::fetchNpmDownloads($project->npm_url);
        }

        if ($project->docker_url) {
            return self::fetchDockerHubPulls($project->docker_url);
        }

        return null;
    }

    /**
     * Docker Hub exposes a cumulative `pull_count` on the public repository
     * endpoint — no auth required. GHCR (ghcr.io) has no equivalent public
     * counter, so only Docker Hub URLs resolve here; a ghcr.io docker_url
     * yields null and the project keeps its previous download value.
     */
    private static function fetchDockerHubPulls(?string $dockerUrl): ?int
    {
        $repo = self::repoFromDockerHubUrl($dockerUrl);

        if (! $repo) {
            return null;
        }

        $response = self::http()
            ->withHeaders(['User-Agent' => 'jeffersongoncalves-site'])
            ->get("https://hub.docker.com/v2/repositories/{$repo}/");

        if (! $response->successful()) {
            return null;
        }

        $pulls = $response->json('pull_count');

        return is_numeric($pulls) ? (int) $pulls : null;
    }

    /**
     * Normalise a Docker Hub URL to the `{namespace}/{repo}` form the v2
     * API expects. Handles namespaced repos (`/r/owner/repo`) and official
     * images (`/_/repo` → `library/repo`). Returns null for anything that
     * isn't a hub.docker.com URL (e.g. a ghcr.io link).
     */
    private static function repoFromDockerHubUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        // Official image: hub.docker.com/_/nginx → library/nginx
        if (preg_match('~hub\.docker\.com/_/([^/?#]+)~i', $url, $m)) {
            return 'library/'.rtrim($m[1], '/');
        }

        // Namespaced: hub.docker.com/r/plausible/analytics → plausible/analytics
        if (preg_match('~hub\.docker\.com/r/([^/?#]+/[^/?#]+)~i', $url, $m)) {
            return rtrim($m[1], '/');
        }

        return null;
    }

    private static function fetchNpmDownloads(?string $npmUrl): ?int
    {
        $package = self::packageFromNpmUrl($npmUrl);

        if (! $package) {
            return null;
        }

        $response = self::http()
            ->withHeaders(['User-Agent' => 'jeffersongoncalves-site'])
            ->get("https://api.npmjs.org/downloads/point/last-month/{$package}");

        if (! $response->successful()) {
            return null;
        }

        $downloads = $response->json('downloads');

        return is_numeric($downloads) ? (int) $downloads : null;
    }

    private static function packageFromNpmUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        // Matches npmjs.com/package/{name} or npmjs.com/package/@scope/name
        if (! preg_match('~npmjs\.com/package/(@[^/?#]+/[^/?#]+|[^/?#]+)~i', $url, $m)) {
            return null;
        }

        return rtrim($m[1], '/');
    }

    /**
     * Re-derive the Packagist URL from the repo's `composer.json` `name` field.
     * Owners that publish under a different vendor than the GitHub owner (e.g.
     * github.com/achyutkneupane/filament-log-viewer publishes as
     * achyutn/filament-log-viewer on Packagist) would otherwise have a broken
     * packagist_url. Returns null when verification is skipped (no current
     * packagist_url, no GitHub URL, no composer.json, fetch error).
     */
    private static function resolvePackagistUrl(Project $project, ?string $defaultBranch): ?string
    {
        if ($project->package_type !== PackageType::Composer || ! $project->github_url) {
            return null;
        }

        $repo = GithubReadme::repoFromUrl($project->github_url);

        if (! $repo) {
            return null;
        }

        $branch = $defaultBranch ?? 'main';

        $headers = ['User-Agent' => 'jeffersongoncalves-site'];

        $response = self::http()
            ->withHeaders($headers)
            ->get("https://raw.githubusercontent.com/{$repo}/{$branch}/composer.json");

        if (! $response->successful()) {
            return null;
        }

        $name = $response->json('name');

        if (! is_string($name) || ! preg_match('~^[a-z0-9_.-]+/[a-z0-9_.-]+$~i', $name)) {
            return null;
        }

        return 'https://packagist.org/packages/'.strtolower($name);
    }

    /**
     * Same idea as resolvePackagistUrl, but reads `name` from `package.json`
     * to keep the npm URL aligned with the published npm package name (which
     * can differ from the GitHub repo name, especially for scoped packages
     * like `@scope/name`).
     */
    private static function resolveNpmUrl(Project $project, ?string $defaultBranch): ?string
    {
        if ($project->package_type !== PackageType::Npm || ! $project->github_url) {
            return null;
        }

        $repo = GithubReadme::repoFromUrl($project->github_url);

        if (! $repo) {
            return null;
        }

        $branch = $defaultBranch ?? 'main';

        $response = self::http()
            ->withHeaders(['User-Agent' => 'jeffersongoncalves-site'])
            ->get("https://raw.githubusercontent.com/{$repo}/{$branch}/package.json");

        if (! $response->successful()) {
            return null;
        }

        // Monorepo roots (tailwindcss, livewire, etc) have `private: true` and
        // a non-publishable name like "@scope/root". Trust the seeded npm_url
        // in that case rather than overwriting it with the root manifest name.
        if ($response->json('private') === true) {
            return null;
        }

        $name = $response->json('name');

        if (! is_string($name) || ! preg_match('#^(@[a-z0-9_.~-]+/)?[a-z0-9_.~-]+$#i', $name)) {
            return null;
        }

        return 'https://www.npmjs.com/package/'.$name;
    }

    private static function fetchPackagistDownloads(?string $packagistUrl): ?int
    {
        $package = self::packageFromPackagistUrl($packagistUrl);

        if (! $package) {
            return null;
        }

        $response = self::http()
            ->withHeaders(['User-Agent' => 'jeffersongoncalves-site'])
            ->get("https://packagist.org/packages/{$package}.json");

        if (! $response->successful()) {
            return null;
        }

        $total = $response->json('package.downloads.total');

        return is_numeric($total) ? (int) $total : null;
    }

    /**
     * Pull `keywords` off the Packagist package document so they can feed the
     * project's topics. Merges keywords across versions (deduped/capped later by
     * ProjectTopics). Empty on any failure.
     *
     * @return list<string>
     */
    private static function fetchPackagistKeywords(?string $packagistUrl): array
    {
        $package = self::packageFromPackagistUrl($packagistUrl);

        if (! $package) {
            return [];
        }

        $response = self::http()
            ->withHeaders(['User-Agent' => 'jeffersongoncalves-site'])
            ->get("https://packagist.org/packages/{$package}.json");

        if (! $response->successful()) {
            return [];
        }

        $versions = $response->json('package.versions');

        if (! is_array($versions)) {
            return [];
        }

        $keywords = [];

        foreach ($versions as $version) {
            if (! is_array($version) || ! is_array($version['keywords'] ?? null)) {
                continue;
            }

            foreach ($version['keywords'] as $keyword) {
                if (is_string($keyword) && $keyword !== '') {
                    $keywords[$keyword] = true;
                }
            }

            if (count($keywords) >= 30) {
                break;
            }
        }

        return array_keys($keywords);
    }

    private static function packageFromPackagistUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (! preg_match('~packagist\.org/packages/([^/]+/[^/?#]+)~i', $url, $m)) {
            return null;
        }

        return rtrim($m[1], '/');
    }

    private static function fetchJetBrainsDownloads(string $idWithSlug): ?int
    {
        // jetbrainsId format: "31190-worktree-env-configurator" — API accepts the leading numeric id
        $id = (int) explode('-', $idWithSlug)[0];

        if ($id <= 0) {
            return null;
        }

        $response = self::http()
            ->withHeaders(['User-Agent' => 'jeffersongoncalves-site'])
            ->get("https://plugins.jetbrains.com/api/plugins/{$id}");

        if (! $response->successful()) {
            return null;
        }

        $downloads = $response->json('downloads');

        return is_numeric($downloads) ? (int) $downloads : null;
    }

    private static function jetBrainsIdFromUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (! preg_match('~plugins\.jetbrains\.com/plugin/([0-9]+(?:-[a-z0-9-]+)?)~i', $url, $m)) {
            return null;
        }

        return $m[1];
    }

    public static function formatDownloads(int $n): string
    {
        if ($n >= 1_000_000) {
            return rtrim(rtrim(number_format($n / 1_000_000, 1, '.', ''), '0'), '.').'M';
        }
        if ($n >= 1_000) {
            return rtrim(rtrim(number_format($n / 1_000, 1, '.', ''), '0'), '.').'k';
        }

        return (string) $n;
    }
}

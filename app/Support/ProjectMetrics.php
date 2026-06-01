<?php

namespace App\Support;

use App\Enums\PackageType;
use App\Enums\ProjectCategory;
use App\Enums\ProjectLanguage;
use App\Exceptions\GithubRateLimitException;
use App\Models\Project;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
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

        // One GraphQL call carries stars + language + topics + default branch +
        // the branch list, so the URL-resolvers and branch repair below reuse it
        // instead of issuing their own REST calls.
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
            // GraphQL query — lets the catalogue facet generic applications by
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

        $repairedOverrides = self::repairBranchOverrides($project, $defaultBranch, $snapshot['branches'] ?? null);
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
     * @param  list<string>|null  $branches  branch names from the GraphQL
     *                                       snapshot (null/empty = no data)
     * @return array<string,string>|null the repaired map, or null when no
     *                                   change is needed / when verification
     *                                   cannot be performed (no branch data,
     *                                   non-Filament project)
     */
    private static function repairBranchOverrides(Project $project, ?string $defaultBranch, ?array $branches): ?array
    {
        if (! is_array($project->versions) || $project->versions === []) {
            return null;
        }

        // Empty/missing branch list means we couldn't read the repo this run —
        // skip rather than rewriting every override to a fallback. A real repo
        // always has at least one branch.
        if ($branches === null || $branches === []) {
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
     * Fetch stars + primary language + topics + default branch + branch list in
     * a single GraphQL call. The 6h freshness window serves a recently-fetched
     * snapshot from cache with zero network, absorbing the admin-save bursts the
     * ProjectObserver triggers. Returns null when the snapshot can't be
     * (re)fetched and nothing is cached.
     *
     * @return array{stars:int, language:?string, topics:list<string>, default_branch:?string, branches:list<string>}|null
     */
    private static function fetchRepoSnapshot(?string $githubUrl): ?array
    {
        $repo = GithubReadme::repoFromUrl($githubUrl);

        if (! $repo) {
            return null;
        }

        $cacheKey = "github:repo-snapshot:{$repo}";

        $cached = Cache::get($cacheKey);
        $cached = is_array($cached) ? $cached : [];
        $cachedPayload = is_array($cached['payload'] ?? null) ? self::normalizeSnapshot($cached['payload']) : null;
        $fetchedAt = is_int($cached['fetched_at'] ?? null) ? $cached['fetched_at'] : 0;

        // Fresh within 6h → serve cached payload, no network at all.
        if ($cachedPayload !== null && (time() - $fetchedAt) < 6 * 3600) {
            return $cachedPayload;
        }

        [$owner, $name] = explode('/', $repo, 2);
        $payload = self::fetchRepoGraphql($owner, $name);

        if ($payload === null) {
            // GraphQL unavailable (no token, network error, repo missing) —
            // serve the last good snapshot rather than wiping the project's
            // metrics on a transient failure.
            return $cachedPayload;
        }

        self::storeSnapshot($cacheKey, $payload);

        return $payload;
    }

    /**
     * One POST /graphql for the whole snapshot. GraphQL has its own
     * 5000-point/hour budget, independent of the REST primary limit — moving
     * this load off REST keeps the REST budget (now just the contributors call)
     * far from the ceiling that triggered the original incident.
     *
     * Returns null when unavailable: no token (GraphQL always requires auth), a
     * network/HTTP error, or a null `repository` (not found / GraphQL error). A
     * rate-limit response — HTTP 403/429, or a 200 carrying a `RATE_LIMITED`
     * error — throws GithubRateLimitException, same as the REST path.
     *
     * @return array{stars:int, language:?string, topics:list<string>, default_branch:?string, branches:list<string>}|null
     */
    private static function fetchRepoGraphql(string $owner, string $name): ?array
    {
        $token = config('services.github.token');

        if (! $token) {
            return null;
        }

        $query = <<<'GQL'
        query($owner: String!, $name: String!) {
          repository(owner: $owner, name: $name) {
            stargazerCount
            primaryLanguage { name }
            repositoryTopics(first: 20) { nodes { topic { name } } }
            defaultBranchRef { name }
            refs(refPrefix: "refs/heads/", first: 100) { nodes { name } }
          }
        }
        GQL;

        $response = self::http()
            ->withHeaders([
                'User-Agent' => 'jeffersongoncalves-site',
                'Authorization' => "Bearer {$token}",
            ])
            ->post('https://api.github.com/graphql', [
                'query' => $query,
                'variables' => ['owner' => $owner, 'name' => $name],
            ]);

        self::throwIfRateLimited($response);

        if (! $response->successful()) {
            Log::warning('GitHub GraphQL failed', ['repo' => "{$owner}/{$name}", 'status' => $response->status()]);

            return null;
        }

        // A primary-limit hit comes back as HTTP 200 with a RATE_LIMITED error.
        $errors = $response->json('errors');
        if (is_array($errors)) {
            foreach ($errors as $error) {
                if (is_array($error) && ($error['type'] ?? null) === 'RATE_LIMITED') {
                    $retryAfter = ((int) $response->header('X-RateLimit-Reset')) - time();

                    throw new GithubRateLimitException(max(60, $retryAfter));
                }
            }
        }

        $repository = $response->json('data.repository');

        if (! is_array($repository)) {
            return null;
        }

        $topics = [];
        foreach ((array) ($repository['repositoryTopics']['nodes'] ?? []) as $node) {
            $topic = is_array($node) ? ($node['topic']['name'] ?? null) : null;
            if (is_string($topic) && $topic !== '') {
                $topics[] = $topic;
            }
        }

        $branches = [];
        foreach ((array) ($repository['refs']['nodes'] ?? []) as $node) {
            $branch = is_array($node) ? ($node['name'] ?? null) : null;
            if (is_string($branch) && $branch !== '') {
                $branches[] = $branch;
            }
        }

        return self::normalizeSnapshot([
            'stars' => $repository['stargazerCount'] ?? 0,
            'language' => $repository['primaryLanguage']['name'] ?? null,
            'topics' => $topics,
            'default_branch' => $repository['defaultBranchRef']['name'] ?? null,
            'branches' => $branches,
        ]);
    }

    /**
     * Coerce a raw repo payload (live response or cached entry) into the strict
     * snapshot shape. Used on both read paths so cached and freshly-fetched
     * snapshots are provably identical in type.
     *
     * @param  array<string, mixed>  $raw
     * @return array{stars:int, language:?string, topics:list<string>, default_branch:?string, branches:list<string>}
     */
    private static function normalizeSnapshot(array $raw): array
    {
        $language = $raw['language'] ?? null;
        $defaultBranch = $raw['default_branch'] ?? null;

        $topics = $raw['topics'] ?? [];
        $topics = is_array($topics)
            ? array_values(array_filter($topics, 'is_string'))
            : [];

        $branches = $raw['branches'] ?? [];
        $branches = is_array($branches)
            ? array_values(array_filter($branches, 'is_string'))
            : [];

        return [
            'stars' => (int) ($raw['stars'] ?? 0),
            'language' => is_string($language) && $language !== '' ? $language : null,
            'topics' => $topics,
            'default_branch' => is_string($defaultBranch) && $defaultBranch !== '' ? $defaultBranch : null,
            'branches' => $branches,
        ];
    }

    /**
     * Persist a snapshot for 7 days. The TTL outlives the 6h freshness window so
     * a stale-but-present snapshot can still be served if a later GraphQL fetch
     * fails, rather than wiping the project's metrics.
     *
     * @param  array{stars:int, language:?string, topics:list<string>, default_branch:?string, branches:list<string>}  $payload
     */
    private static function storeSnapshot(string $cacheKey, array $payload): void
    {
        Cache::put($cacheKey, [
            'payload' => $payload,
            'fetched_at' => time(),
        ], now()->addDays(7));
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

        $candidate = 'https://packagist.org/packages/'.strtolower($name);

        // A composer.json `name` alone does not prove the package is published as
        // that vendor/name — app skeletons and tutorials ship `laravel/laravel`
        // without owning it. Only adopt the derived URL when Packagist's
        // `repository` points back at this repo (same guard the importer uses).
        // A transient UNKNOWN (rate limit) returns null = no change, leaving the
        // existing value untouched rather than risking a wrong attribution.
        if (ProjectImporter::packagistUrlOwnershipStatus($candidate, (string) $project->github_url) !== ProjectImporter::LINK_OWNED) {
            return null;
        }

        return $candidate;
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

        $candidate = 'https://www.npmjs.com/package/'.$name;

        // Same ownership guard as the packagist path: a borrowed package.json
        // `name` (e.g. mpvue shipping `"name": "vue"`) must not claim a foreign
        // npm package. UNKNOWN returns null = no change.
        if (ProjectImporter::npmUrlOwnershipStatus($candidate, (string) $project->github_url) !== ProjectImporter::LINK_OWNED) {
            return null;
        }

        return $candidate;
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

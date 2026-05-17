<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProjectMetrics
{
    public static function sync(Project $project): bool
    {
        $changed = false;

        $stars = self::fetchStars($project->github_url);
        if ($stars !== null && $stars !== $project->stars) {
            $project->stars = $stars;
            $changed = true;
        }

        $downloads = self::fetchDownloads($project);
        if ($downloads !== null && $downloads !== $project->downloads) {
            $project->downloads = $downloads;
            $project->downloads_label = self::formatDownloads($downloads);
            $changed = true;
        }

        $repairedOverrides = self::repairBranchOverrides($project);
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
    private static function repairBranchOverrides(Project $project): ?array
    {
        if (! is_array($project->versions) || $project->versions === []) {
            return null;
        }

        $branches = self::fetchBranches($project->github_url);

        if ($branches === null) {
            return null;
        }

        $current = is_array($project->branch_overrides) ? $project->branch_overrides : [];
        $defaultBranch = self::fetchDefaultBranch($project->github_url);

        $next = [];
        foreach (array_values($project->versions) as $i => $_) {
            $autoBranch = ($i + 1).'.x';
            $currentValue = isset($current[$autoBranch]) ? trim((string) $current[$autoBranch]) : '';

            if ($currentValue !== '' && in_array($currentValue, $branches, true)) {
                $next[$autoBranch] = $currentValue;

                continue;
            }

            if (in_array($autoBranch, $branches, true)) {
                $next[$autoBranch] = $autoBranch;

                continue;
            }

            if ($defaultBranch !== null && in_array($defaultBranch, $branches, true)) {
                $next[$autoBranch] = $defaultBranch;

                continue;
            }

            $next[$autoBranch] = $currentValue !== '' ? $currentValue : $autoBranch;
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

        $names = [];
        $page = 1;

        do {
            $response = Http::timeout(8)
                ->withHeaders($headers)
                ->get("https://api.github.com/repos/{$repo}/branches", [
                    'per_page' => 100,
                    'page' => $page,
                ]);

            if (! $response->successful()) {
                Log::warning('GitHub branches API failed', ['repo' => $repo, 'status' => $response->status()]);

                return null;
            }

            $batch = (array) $response->json();
            foreach ($batch as $branch) {
                if (is_array($branch) && isset($branch['name']) && is_string($branch['name'])) {
                    $names[] = $branch['name'];
                }
            }

            $page++;
        } while (count($batch) === 100 && $page <= 5);

        return $names;
    }

    private static function fetchDefaultBranch(?string $githubUrl): ?string
    {
        $repo = GithubReadme::repoFromUrl($githubUrl);

        if (! $repo) {
            return null;
        }

        $headers = ['User-Agent' => 'jeffersongoncalves-site', 'Accept' => 'application/vnd.github+json'];

        if ($token = config('services.github.token')) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        $response = Http::timeout(8)
            ->withHeaders($headers)
            ->get("https://api.github.com/repos/{$repo}");

        if (! $response->successful()) {
            return null;
        }

        $branch = $response->json('default_branch');

        return is_string($branch) && $branch !== '' ? $branch : null;
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

        $page = 1;

        do {
            $response = Http::timeout(8)
                ->withHeaders($headers)
                ->get("https://api.github.com/repos/{$repo}/contributors", [
                    'per_page' => 100,
                    'page' => $page,
                    'anon' => 'false',
                ]);

            if (! $response->successful()) {
                Log::warning('GitHub contributors API failed', ['repo' => $repo, 'status' => $response->status()]);

                return null;
            }

            $batch = (array) $response->json();

            foreach ($batch as $contributor) {
                if (! is_array($contributor) || ! isset($contributor['login'])) {
                    continue;
                }

                if (strtolower((string) $contributor['login']) === $username) {
                    return (int) ($contributor['contributions'] ?? 0);
                }
            }

            $page++;
        } while (count($batch) === 100 && $page <= 5);

        return 0;
    }

    private static function fetchStars(?string $githubUrl): ?int
    {
        $repo = GithubReadme::repoFromUrl($githubUrl);

        if (! $repo) {
            return null;
        }

        $headers = ['User-Agent' => 'jeffersongoncalves-site', 'Accept' => 'application/vnd.github+json'];

        if ($token = config('services.github.token')) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        $response = Http::timeout(8)
            ->withHeaders($headers)
            ->get("https://api.github.com/repos/{$repo}");

        if (! $response->successful()) {
            Log::warning('GitHub API failed', ['repo' => $repo, 'status' => $response->status()]);

            return null;
        }

        return (int) ($response->json('stargazers_count') ?? 0);
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

        return null;
    }

    private static function fetchNpmDownloads(?string $npmUrl): ?int
    {
        $package = self::packageFromNpmUrl($npmUrl);

        if (! $package) {
            return null;
        }

        $response = Http::timeout(8)
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

    private static function fetchPackagistDownloads(?string $packagistUrl): ?int
    {
        $package = self::packageFromPackagistUrl($packagistUrl);

        if (! $package) {
            return null;
        }

        $response = Http::timeout(8)
            ->withHeaders(['User-Agent' => 'jeffersongoncalves-site'])
            ->get("https://packagist.org/packages/{$package}.json");

        if (! $response->successful()) {
            return null;
        }

        $total = $response->json('package.downloads.total');

        return is_numeric($total) ? (int) $total : null;
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

        $response = Http::timeout(8)
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

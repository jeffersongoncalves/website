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

        if ($changed) {
            $project->last_synced_at = now();
            $project->save();
        }

        return $changed;
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
            return self::fetchPackagistDownloads($project->github_url);
        }

        return null;
    }

    private static function fetchPackagistDownloads(?string $githubUrl): ?int
    {
        $repo = GithubReadme::repoFromUrl($githubUrl);

        if (! $repo) {
            return null;
        }

        $response = Http::timeout(8)
            ->withHeaders(['User-Agent' => 'jeffersongoncalves-site'])
            ->get("https://packagist.org/packages/{$repo}.json");

        if (! $response->successful()) {
            return null;
        }

        $total = $response->json('package.downloads.total');

        return is_numeric($total) ? (int) $total : null;
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

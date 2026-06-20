<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\GithubRateLimitException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * GitHub/raw.githubusercontent HTTP layer for the importer. Wraps the REST
 * calls (repo, branches, per-directory README) and the raw-content fetches
 * (manifests, file-existence HEADs), threading the API token and centralising
 * rate-limit detection so callers get a thrown GithubRateLimitException rather
 * than a silent null during a limit window.
 */
class GithubClient
{
    /** Repo resolves on GitHub. */
    public const REPO_EXISTS = 'exists';

    /** GitHub answered 404 — repo deleted, renamed, or now private. */
    public const REPO_GONE = 'gone';

    /** Couldn't determine (network error, 5xx, or a non-rate-limit refusal) —
     *  callers must NOT treat this as gone. */
    public const REPO_UNKNOWN = 'unknown';

    /**
     * Tri-state existence probe for a repo, distinguishing a definitive 404
     * (safe to prune) from a transient/unknown failure (never prune on doubt).
     * A rate-limit response throws GithubRateLimitException to the caller.
     *
     * @throws GithubRateLimitException
     */
    public static function repoStatus(string $repoSlug): string
    {
        $response = self::githubGet("https://api.github.com/repos/{$repoSlug}");

        if ($response === null) {
            return self::REPO_UNKNOWN; // network/timeout/DNS — logged in githubGet
        }

        if ($response->status() === 404) {
            return self::REPO_GONE;
        }

        if ($response->successful()) {
            return self::REPO_EXISTS;
        }

        return self::REPO_UNKNOWN; // 5xx / 451 / other — don't assume gone
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function fetchRepo(string $repoSlug): ?array
    {
        $response = self::githubGet("https://api.github.com/repos/{$repoSlug}");

        if ($response === null || ! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    public static function fetchDefaultBranchForSlug(string $repoSlug): ?string
    {
        $data = self::fetchRepo($repoSlug);

        if ($data === null) {
            return null;
        }

        $branch = $data['default_branch'] ?? null;

        return is_string($branch) && $branch !== '' ? $branch : null;
    }

    /**
     * Probe GitHub's per-directory README endpoint to verify a monorepo
     * subfolder actually ships a README. Some monorepo packages (e.g.
     * `alpinejs/alpine/packages/anchor`) declare a `directory` in their
     * package.json without putting a README alongside the source — the
     * importer flags the import so the editor knows the readme rendering
     * will fall back to the repo root.
     */
    public static function subdirectoryHasReadme(string $repoSlug, string $directory): bool
    {
        $response = self::githubGet("https://api.github.com/repos/{$repoSlug}/readme/{$directory}");

        return $response !== null && $response->successful();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function fetchManifest(string $repoSlug, string $branch, string $file): ?array
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'jeffersongoncalves-site'])
                ->get("https://raw.githubusercontent.com/{$repoSlug}/{$branch}/{$file}");
        } catch (Throwable $e) {
            self::logFailure('github_manifest', "{$repoSlug}/{$branch}/{$file}", $e);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * @return list<string>
     */
    public static function fetchBranches(string $repoSlug): array
    {
        $response = self::githubGet("https://api.github.com/repos/{$repoSlug}/branches", ['per_page' => 100]);

        if ($response === null || ! $response->successful()) {
            return [];
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
     * HEAD a raw.githubusercontent.com path to verify a file exists on a
     * given branch without downloading it. Used by the Docker detector
     * to check for docker-compose / compose files without parsing JSON.
     */
    public static function fileExists(string $repoSlug, string $branch, string $file): bool
    {
        try {
            $response = Http::timeout(6)
                ->withHeaders(['User-Agent' => 'jeffersongoncalves-site'])
                ->head("https://raw.githubusercontent.com/{$repoSlug}/{$branch}/{$file}");
        } catch (Throwable $e) {
            self::logFailure('github_file_head', "{$repoSlug}/{$branch}/{$file}", $e);

            return false;
        }

        return $response->successful();
    }

    /**
     * @param  array<string, mixed>  $params
     *
     * @throws GithubRateLimitException when GitHub answers with a rate-limit 403/429
     */
    private static function githubGet(string $url, array $params = []): ?Response
    {
        $headers = ['User-Agent' => 'jeffersongoncalves-site', 'Accept' => 'application/vnd.github+json'];

        if ($token = config('services.github.token')) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        try {
            $response = Http::timeout(8)->withHeaders($headers)->get($url, $params);
        } catch (Throwable $e) {
            self::logFailure('github_api', $url, $e);

            return null;
        }

        // Rate-limit detection runs OUTSIDE the catch above so the thrown
        // exception propagates to the caller instead of being swallowed and
        // logged as a generic fetch failure. A null/incomplete repo during a
        // rate-limit window would otherwise let the import silently fall back
        // to a default category — the queued jobs catch this and release.
        self::throwIfRateLimited($response);

        return $response;
    }

    /**
     * Raise GithubRateLimitException when GitHub signals a rate limit: the
     * primary limit (403 + `X-RateLimit-Remaining: 0`) or the secondary/abuse
     * limit (403/429 carrying a `Retry-After`). Mirrors the detection in
     * ProjectMetrics so the importer degrades the same way under pressure —
     * the dispatching job releases back to the queue until the window resets.
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

    /**
     * Log an outbound-fetch exception with enough context to tell a timeout /
     * DNS / TLS failure apart from a clean non-2xx response (those return their
     * own sentinel without throwing).
     */
    private static function logFailure(string $context, string $target, Throwable $e): void
    {
        Log::warning('ProjectImporter outbound fetch failed', [
            'context' => $context,
            'target' => $target,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }
}

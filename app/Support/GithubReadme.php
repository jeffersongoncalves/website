<?php

namespace App\Support;

use App\Models\ReadmeCache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

class GithubReadme
{
    /**
     * Minutes during which a freshly verified README is served straight from
     * disk without issuing even a conditional request to GitHub.
     */
    private const CHECK_INTERVAL_MINUTES = 10;

    private const DISK = 'github';

    /**
     * Return the rendered README HTML for a repo, backed by a disk cache.
     *
     * Flow: within the check window the cached file is served with no GitHub
     * call at all. Otherwise a conditional request (`If-None-Match`) is made —
     * a `304 Not Modified` reuses the cached file (and does not count against
     * the rate limit), a `200` re-renders and rewrites the disk file. On a
     * network/API error the stale file is served if present.
     */
    public static function fetchHtml(string $githubUrl, ?string $ref = null): ?string
    {
        $repo = self::repoFromUrl($githubUrl);

        if (! $repo) {
            return null;
        }

        $refKey = $ref ?: 'default';
        $cache = ReadmeCache::query()->firstOrNew(['repo' => $repo, 'ref' => $refKey]);
        $disk = Storage::disk(self::DISK);

        $hasFile = $cache->html_path !== null && $disk->exists($cache->html_path);

        // Skip window — recently verified, serve the file without touching GitHub.
        if ($hasFile && $cache->checked_at !== null
            && $cache->checked_at->gt(now()->subMinutes(self::CHECK_INTERVAL_MINUTES))) {
            return $disk->get($cache->html_path);
        }

        $result = self::fetchConditional($repo, $ref, $cache->etag);

        // 304 Not Modified — README unchanged, reuse the cached file.
        if ($result['status'] === 304 && $hasFile) {
            $cache->checked_at = now();
            $cache->save();

            return $disk->get($cache->html_path);
        }

        // 200 — content changed (or first fetch): re-render and store on disk.
        if ($result['status'] === 200 && $result['body'] !== null) {
            $branch = $ref ?: self::defaultBranch($repo, $cache);
            $markdown = self::rewriteRelativeAssets($result['body'], $repo, $branch);
            $html = self::renderMarkdown($markdown);

            $path = 'readme/'.str_replace('/', '__', $repo).'/'.$refKey.'.html';
            $disk->put($path, $html);

            $cache->fill([
                'etag' => $result['etag'],
                'default_branch' => $branch,
                'html_path' => $path,
                'fetched_at' => now(),
                'checked_at' => now(),
            ])->save();

            return $html;
        }

        // Network/API error — fall back to the stale cached file if present.
        if ($hasFile) {
            $cache->checked_at = now();
            $cache->save();

            return $disk->get($cache->html_path);
        }

        return null;
    }

    /**
     * Filament plugin branches follow a per-repo sequence: the lowest supported
     * Filament version maps to branch `1.x`, next to `2.x`, etc.
     * Example: plugin supporting [v3,v4,v5] → 1.x, 2.x, 3.x.
     * Plugin supporting [v4,v5]            → 1.x, 2.x.
     * Plugin supporting [v5]               → 1.x.
     *
     * @param  list<string>  $supportedVersions  ordered ascending (e.g. ['v3','v4','v5'])
     */
    public static function branchForFilamentVersion(string $version, array $supportedVersions): ?string
    {
        $idx = array_search($version, $supportedVersions, true);

        if ($idx === false) {
            return null;
        }

        return ($idx + 1).'.x';
    }

    public static function repoFromUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (! preg_match('~github\.com/([^/]+/[^/?#]+)~i', $url, $m)) {
            return null;
        }

        return rtrim($m[1], '/');
    }

    /**
     * Issue a conditional request for the repo README.
     *
     * @return array{status:int, body:?string, etag:?string}
     */
    private static function fetchConditional(string $repo, ?string $ref, ?string $etag): array
    {
        $headers = self::githubHeaders(['Accept' => 'application/vnd.github.raw']);

        if ($etag !== null && $etag !== '') {
            $headers['If-None-Match'] = $etag;
        }

        $response = Http::timeout(8)
            ->withHeaders($headers)
            ->get("https://api.github.com/repos/{$repo}/readme", $ref ? ['ref' => $ref] : []);

        if ($response->status() === 304) {
            return ['status' => 304, 'body' => null, 'etag' => $etag];
        }

        if ($response->successful()) {
            $newEtag = $response->header('ETag');

            return [
                'status' => 200,
                'body' => $response->body(),
                'etag' => $newEtag !== '' ? $newEtag : $etag,
            ];
        }

        return ['status' => $response->status(), 'body' => null, 'etag' => $etag];
    }

    private static function defaultBranch(string $repo, ReadmeCache $cache): ?string
    {
        if ($cache->default_branch !== null && $cache->default_branch !== '') {
            return $cache->default_branch;
        }

        $response = Http::timeout(8)
            ->withHeaders(self::githubHeaders(['Accept' => 'application/vnd.github+json']))
            ->get("https://api.github.com/repos/{$repo}");

        return $response->successful() ? $response->json('default_branch') : null;
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private static function githubHeaders(array $extra = []): array
    {
        $headers = array_merge(['User-Agent' => 'jeffersongoncalves-site'], $extra);

        if ($token = config('services.github.token')) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return $headers;
    }

    private static function rewriteRelativeAssets(string $markdown, string $repo, ?string $ref = null): string
    {
        $branch = $ref ?: 'HEAD';
        $base = "https://raw.githubusercontent.com/{$repo}/{$branch}/";

        return preg_replace_callback(
            '~(!\[[^\]]*\]\()([^)]+)(\))~',
            function ($m) use ($base) {
                $src = trim($m[2]);

                if (preg_match('#^(https?://|data:|/)#i', $src)) {
                    return $m[0];
                }

                return $m[1].$base.ltrim($src, './').$m[3];
            },
            $markdown
        );
    }

    private static function renderMarkdown(string $markdown): string
    {
        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            'heading_permalink' => [
                'symbol' => '#',
                'html_class' => 'md-anchor',
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new HeadingPermalinkExtension);

        return (new MarkdownConverter($environment))->convert($markdown)->getContent();
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ReadmeCache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

        // Only send If-None-Match when the cached file is actually on disk.
        // Otherwise a 304 (etag still matches) would leave us with no body to
        // render and no file to fall back on — yielding a permanent null. This
        // happens whenever the ReadmeCache row survives but its file doesn't
        // (e.g. a fresh deploy disk with the DB carried over). Forcing a full
        // 200 in that case re-renders and re-persists the file.
        $result = self::fetchConditional($repo, $ref, $hasFile ? $cache->etag : null);

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
            $html = self::rewriteRelativeLinks($html, $repo, $branch);

            $path = 'readme/'.str_replace('/', '__', $repo).'/'.$refKey.'.html';

            // Cache write is best-effort — a permission-denied volume must
            // not break the page render. Serve the freshly rendered HTML
            // even when persistence fails.
            try {
                $disk->put($path, $html);

                $cache->fill([
                    'etag' => $result['etag'],
                    'default_branch' => $branch,
                    'html_path' => $path,
                    'fetched_at' => now(),
                    'checked_at' => now(),
                ])->save();
            } catch (\Throwable $e) {
                Log::warning('GithubReadme cache write failed', [
                    'repo' => $repo,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }

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

    /**
     * Reverse of branchForFilamentVersion: given a branch name (raw or
     * after applying branch_overrides), return the user-facing version
     * (e.g. 'v3', 'v4', 'v5') or null when the branch is not tracked.
     *
     * @param  list<string>  $supportedVersions  ordered ascending (e.g. ['v3','v4','v5'])
     * @param  array<string,string>  $branchOverrides  auto-branch → real-branch map
     */
    public static function branchToVersion(string $branch, array $supportedVersions, array $branchOverrides = []): ?string
    {
        foreach ($supportedVersions as $i => $version) {
            $autoBranch = ($i + 1).'.x';
            $realBranch = isset($branchOverrides[$autoBranch]) && trim((string) $branchOverrides[$autoBranch]) !== ''
                ? trim((string) $branchOverrides[$autoBranch])
                : $autoBranch;

            if ($branch === $realBranch || $branch === $autoBranch) {
                return $version;
            }
        }

        return null;
    }

    /**
     * Rewrite anchors in rendered README HTML that point to the project's
     * OWN GitHub repo `/tree/{branch}` URLs so they target the local
     * `projects.show?v={version}` route instead. Untracked branches and
     * non-self links are left untouched.
     *
     * @param  list<string>  $supportedVersions
     * @param  array<string,string>  $branchOverrides
     */
    public static function rewriteSelfRepoLinks(
        string $html,
        ?string $githubUrl,
        string $projectSlug,
        array $supportedVersions,
        array $branchOverrides = []
    ): string {
        $repo = self::repoFromUrl($githubUrl);

        if (! $repo || $supportedVersions === []) {
            return $html;
        }

        [$owner, $name] = explode('/', $repo, 2);
        $ownerPattern = preg_quote($owner, '~');
        $namePattern = preg_quote($name, '~');

        $pattern = "~href=\"https?://github\\.com/{$ownerPattern}/{$namePattern}/tree/([^\"/#?]+)[^\"]*\"~i";

        return preg_replace_callback(
            $pattern,
            function (array $m) use ($projectSlug, $supportedVersions, $branchOverrides): string {
                $branch = $m[1];
                $version = self::branchToVersion($branch, $supportedVersions, $branchOverrides);

                if ($version === null) {
                    return $m[0];
                }

                $url = route('projects.show', ['slug' => $projectSlug, 'v' => $version]);

                return 'href="'.htmlspecialchars($url, ENT_QUOTES | ENT_HTML5).'"';
            },
            $html
        ) ?? $html;
    }

    /**
     * Decorate absolute http(s) anchors that point off-site with
     * target="_blank" + rel="nofollow noopener". Existing target/rel
     * attributes are preserved (never duplicated, never overwritten).
     */
    public static function markExternalLinks(string $html, string $selfHost): string
    {
        $selfHost = strtolower(trim($selfHost));

        return preg_replace_callback(
            '~<a([^>]*?)\shref="(https?://[^"]+)"([^>]*)>~i',
            function (array $m) use ($selfHost): string {
                $url = $m[2];
                $host = strtolower((string) parse_url($url, PHP_URL_HOST));

                if ($host === '' || $host === $selfHost) {
                    return $m[0];
                }

                $attrs = $m[1].$m[3];
                $extras = '';

                if (! preg_match('/\btarget\s*=/i', $attrs)) {
                    $extras .= ' target="_blank"';
                }

                if (! preg_match('/\brel\s*=/i', $attrs)) {
                    $extras .= ' rel="nofollow noopener"';
                }

                if ($extras === '') {
                    return $m[0];
                }

                return '<a'.$m[1].' href="'.$url.'"'.$m[3].$extras.'>';
            },
            $html
        ) ?? $html;
    }

    /**
     * Defer README images: `decoding="async"` on all, `loading="lazy"` on every
     * image except the first. The first image (usually the project's hero/logo
     * banner) stays eager so it isn't deprioritised as the LCP candidate; the
     * rest load as they near the viewport, cutting bandwidth and the layout
     * shift from many below-the-fold images decoding at once. Existing
     * loading/decoding attributes are preserved (never duplicated).
     */
    public static function lazyloadImages(string $html): string
    {
        $index = 0;

        return preg_replace_callback(
            '~<img\b([^>]*?)>~i',
            function (array $m) use (&$index): string {
                $attrs = $m[1];
                $isFirst = $index === 0;
                $index++;

                $extras = '';

                if (! preg_match('/\bdecoding\s*=/i', $attrs)) {
                    $extras .= ' decoding="async"';
                }

                if (! $isFirst && ! preg_match('/\bloading\s*=/i', $attrs)) {
                    $extras .= ' loading="lazy"';
                }

                if ($extras === '') {
                    return $m[0];
                }

                return '<img'.$attrs.$extras.'>';
            },
            $html
        ) ?? $html;
    }

    /**
     * Wrap every rendered README `<table>` in a horizontally-scrollable
     * container so wide tables (e.g. a 16-column comparison matrix) scroll
     * inside the article column instead of overflowing the page. Markdown
     * tables never nest, so a non-greedy match is safe.
     */
    public static function wrapTables(string $html): string
    {
        return preg_replace(
            '~<table\b[^>]*>.*?</table>~is',
            '<div class="md-table-scroll">$0</div>',
            $html
        ) ?? $html;
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

    /**
     * Rewrite relative `<a href="...">` URLs in rendered README HTML into
     * absolute GitHub blob URLs. README links like `[License](LICENSE)` come
     * out as `<a href="LICENSE">` and would otherwise resolve to a 404 under
     * the local site path. Absolute URLs, mailto/tel/hash links and root-
     * relative paths are passed through unchanged.
     */
    public static function rewriteRelativeLinks(string $html, string $repo, ?string $ref = null): string
    {
        $branch = $ref ?: 'HEAD';
        $base = "https://github.com/{$repo}/blob/{$branch}/";

        return preg_replace_callback(
            '~<a([^>]*?)\shref="([^"]+)"([^>]*)>~i',
            function (array $m) use ($base): string {
                $href = trim($m[2]);

                if ($href === '' || preg_match('~^(https?:|//|mailto:|tel:|javascript:|data:|#|/)~i', $href)) {
                    return $m[0];
                }

                $url = $base.ltrim($href, './');

                return '<a'.$m[1].' href="'.$url.'"'.$m[3].'>';
            },
            $html
        ) ?? $html;
    }

    public static function rewriteRelativeAssets(string $markdown, string $repo, ?string $ref = null): string
    {
        $branch = $ref ?: 'HEAD';
        $base = "https://raw.githubusercontent.com/{$repo}/{$branch}/";

        $rewrite = static function (string $src) use ($base): string {
            $src = trim($src);

            if (preg_match('#^(https?://|data:|/)#i', $src)) {
                return $src;
            }

            // Drop the GitHub `?raw=true` hint — raw.githubusercontent serves raw bytes already.
            $clean = preg_replace('/[?&]raw=true\b/i', '', $src) ?? $src;

            return $base.ltrim($clean, './');
        };

        // Markdown image syntax: ![alt](src)
        $markdown = preg_replace_callback(
            '~(!\[[^\]]*\]\()([^)]+)(\))~',
            fn ($m) => $m[1].$rewrite($m[2]).$m[3],
            $markdown
        ) ?? $markdown;

        // Raw HTML images: <img ... src="src" ...> — common in README logo/banner blocks.
        return preg_replace_callback(
            '~(<img\b[^>]*?\ssrc=")([^"]+)(")~i',
            fn ($m) => $m[1].$rewrite($m[2]).$m[3],
            $markdown
        ) ?? $markdown;
    }

    private static function renderMarkdown(string $markdown): string
    {
        // README output is sanitised later in ProjectShowPage before display.
        return Markdown::render($markdown, headingPermalinks: true);
    }
}

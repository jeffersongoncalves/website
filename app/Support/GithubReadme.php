<?php

declare(strict_types=1);

namespace App\Support;

use JeffersonGoncalves\GitHubReadme\GitHubReadme as Readme;
use JeffersonGoncalves\Markdown\Markdown;

/**
 * App-side facade over jeffersongoncalves/laravel-github-readme.
 *
 * The generic fetch/cache/render + HTML post-processing lives in the package
 * (which renders through jeffersongoncalves/laravel-markdown via the
 * `github-readme.renderer` callable wired in AppServiceProvider). Only the
 * portfolio-specific glue stays here: the Filament version⇄branch mapping,
 * the self-repo `/tree/{branch}` → `projects.show?v=` link rewrite, and
 * routing outbound README links through OutboundLink — all reference this
 * app's routes/short-url system, not something the generic package knows about.
 */
class GithubReadme
{
    public static function fetchHtml(string $githubUrl, ?string $ref = null): ?string
    {
        return Readme::fetchHtml($githubUrl, $ref);
    }

    /**
     * Renderer wired into `config('github-readme.renderer')` as an array
     * callable (serializable, so `config:cache` works) — renders README
     * markdown through jeffersongoncalves/laravel-markdown (GFM + heading
     * permalinks + server-side syntax highlighting) instead of the package's
     * plain CommonMark default. Output is sanitised later before display.
     */
    public static function renderMarkdown(string $markdown): string
    {
        return Markdown::render($markdown, headingPermalinks: true);
    }

    public static function repoFromUrl(?string $url): ?string
    {
        return Readme::repoFromUrl($url);
    }

    public static function markExternalLinks(string $html, string $selfHost): string
    {
        return Readme::markExternalLinks($html, $selfHost);
    }

    public static function lazyloadImages(string $html): string
    {
        return Readme::lazyloadImages($html);
    }

    /**
     * Stamp a blank `alt=""` on any `<img>` still missing the attribute.
     * Markdown `![]()` syntax always emits one, but READMEs frequently embed
     * raw `<img>` HTML (badge/banner rows) with none at all — an empty,
     * decorative alt is the safe default for content we don't control,
     * matching the site's own logo images.
     */
    public static function ensureImageAlt(string $html): string
    {
        return preg_replace_callback(
            '~<img\b([^>]*?)>~i',
            function (array $m): string {
                $attrs = $m[1];

                return preg_match('/\balt\s*=/i', $attrs) === 1
                    ? $m[0]
                    : '<img'.$attrs.' alt="">';
            },
            $html
        ) ?? $html;
    }

    public static function wrapTables(string $html): string
    {
        return Readme::wrapTables($html);
    }

    public static function rewriteRelativeLinks(string $html, string $repo, ?string $ref = null): string
    {
        return Readme::rewriteRelativeLinks($html, $repo, $ref);
    }

    public static function rewriteRelativeAssets(string $markdown, string $repo, ?string $ref = null): string
    {
        return Readme::rewriteRelativeAssets($markdown, $repo, $ref);
    }

    /**
     * Route every absolute `<a href="...">` in rendered README HTML through
     * OutboundLink, so README link clicks get counted the same as every other
     * outbound link on the site. OutboundLink::resolveMany() no-ops on
     * same-host URLs, so the local `projects.show` links left by
     * rewriteSelfRepoLinks() (and any other self-host anchor) pass through
     * untouched.
     *
     * Every href is extracted up front and resolved in ONE batch pass
     * instead of each `<a>` triggering its own cache+DB round trip during
     * the regex replace — a big awesome-list can carry thousands of links,
     * and doing that sequentially once turned into a multi-minute render
     * (confirmed: a 504 on awesome-selfhosted/awesome-selfhosted).
     *
     * Must run AFTER markExternalLinks() — that method tags a link as
     * external by comparing its href host, so it needs the real destination
     * host still in the href, not the short-url host this rewrite produces.
     */
    public static function rewriteOutboundLinks(string $html): string
    {
        if (preg_match_all('~<a[^>]*?\shref="(https?://[^"]+)"~i', $html, $hrefs) === false || $hrefs[1] === []) {
            return $html;
        }

        $urls = array_map(
            static fn (string $href): string => html_entity_decode($href, ENT_QUOTES | ENT_HTML5),
            $hrefs[1]
        );

        // Deep GitHub sub-pages (an issue/PR permalink, a file browsed via
        // blob/tree/raw) are single-use noise, not a destination worth
        // tracking — a README with an auto-generated "recent issues" list or
        // a big docs tree otherwise mints hundreds of short urls nobody ever
        // clicks (confirmed in production: hkuds/vibe-trading alone minted
        // 696, zero clicks). These stay as real, working, untracked links.
        $resolved = OutboundLink::resolveMany(array_values(array_filter(
            $urls,
            static fn (string $url): bool => ! self::isNoisyGithubSubpage($url)
        )));

        return preg_replace_callback(
            '~<a([^>]*?)\shref="(https?://[^"]+)"([^>]*)>~i',
            function (array $m) use ($resolved): string {
                $url = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5);
                $short = $resolved[$url] ?? $url;

                return '<a'.$m[1].' href="'.htmlspecialchars($short, ENT_QUOTES | ENT_HTML5).'"'.$m[3].'>';
            },
            $html
        ) ?? $html;
    }

    /**
     * Whether a URL is a deep GitHub sub-page not worth minting a short url
     * for: an issue/PR permalink, or a file/directory browsed via
     * blob/tree/raw. These are single-use (an issue number, a specific
     * file path) rather than a destination someone links to repeatedly, so
     * the click count is meaningless and the row is pure bloat.
     */
    private static function isNoisyGithubSubpage(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || strcasecmp($host, 'github.com') !== 0) {
            return false;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);

        return (bool) preg_match('~^/[^/]+/[^/]+/(issues|pull|blob|tree|raw)(/|$)~i', $path);
    }

    /**
     * Numerically sort Filament version strings ("v3", "v4", "v5", ...) by
     * major and dedupe — the canonical order branchForFilamentVersion() and
     * branchToVersion() index against. A persisted `versions` array isn't
     * guaranteed to already be in this order (an import or an admin edit can
     * leave it scrambled), and indexing an out-of-order array silently shifts
     * every version's auto-branch onto the wrong one.
     *
     * @param  list<string>  $versions
     * @return list<string>
     */
    public static function sortedVersions(array $versions): array
    {
        $sorted = array_values(array_unique($versions));

        usort($sorted, static fn (string $a, string $b): int => (int) ltrim($a, 'vV') <=> (int) ltrim($b, 'vV'));

        return $sorted;
    }

    /**
     * Rewrite `<img src="...">` pointing at a GitHub asset host (raw content,
     * camo, user-uploaded images, avatars) to our own cached proxy — see
     * ReadmeImageCache's docblock for why. Any other host (shields.io badges,
     * a personal CDN, ...) is left as a direct hotlink.
     */
    public static function proxyReadmeImages(string $html): string
    {
        return preg_replace_callback(
            '~(<img\b[^>]*?\ssrc=")([^"]+)(")~i',
            function (array $m): string {
                $src = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5);

                if (! ReadmeImageCache::isAllowedHost($src)) {
                    return $m[0];
                }

                $proxied = route('readme-image.show', ['encoded' => ReadmeImageCache::encode($src)]);

                return $m[1].htmlspecialchars($proxied, ENT_QUOTES | ENT_HTML5).$m[3];
            },
            $html
        ) ?? $html;
    }

    /**
     * Filament plugin branches follow a per-repo sequence: the lowest supported
     * Filament version maps to branch `1.x`, next to `2.x`, etc.
     * Example: plugin supporting [v3,v4,v5] → 1.x, 2.x, 3.x.
     * Plugin supporting [v4,v5]            → 1.x, 2.x.
     * Plugin supporting [v5]               → 1.x.
     *
     * @param  list<string>  $supportedVersions  need not be pre-sorted — see sortedVersions()
     */
    public static function branchForFilamentVersion(string $version, array $supportedVersions): ?string
    {
        $idx = array_search($version, self::sortedVersions($supportedVersions), true);

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
     * @param  list<string>  $supportedVersions  need not be pre-sorted — see sortedVersions()
     * @param  array<string,string>  $branchOverrides  auto-branch → real-branch map
     */
    public static function branchToVersion(string $branch, array $supportedVersions, array $branchOverrides = []): ?string
    {
        foreach (self::sortedVersions($supportedVersions) as $i => $version) {
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
}

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
 * portfolio-specific glue stays here: the Filament version⇄branch mapping and
 * the self-repo `/tree/{branch}` → `projects.show?v=` link rewrite, which both
 * reference this app's routes and version conventions.
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
}

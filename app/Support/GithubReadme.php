<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

class GithubReadme
{
    public static function fetchHtml(string $githubUrl, ?string $ref = null, int $ttlMinutes = 60): ?string
    {
        $repo = self::repoFromUrl($githubUrl);

        if (! $repo) {
            return null;
        }

        $cacheKey = "readme.html.{$repo}." . ($ref ?: 'default');

        return Cache::remember($cacheKey, now()->addMinutes($ttlMinutes), function () use ($repo, $ref) {
            $result = self::fetchMarkdown($repo, $ref);

            if ($result === null) {
                return null;
            }

            $markdown = self::rewriteRelativeAssets($result['body'], $repo, $result['ref']);

            return self::renderMarkdown($markdown);
        });
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

        return ($idx + 1) . '.x';
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
     * @return array{body:string,ref:?string}|null
     */
    private static function fetchMarkdown(string $repo, ?string $ref = null): ?array
    {
        if ($ref && ($body = self::fetchReadmeForRef($repo, $ref)) !== null) {
            return ['body' => $body, 'ref' => $ref];
        }

        $default = self::fetchDefaultBranch($repo);

        if ($default !== null && ($body = self::fetchReadmeForRef($repo, $default)) !== null) {
            return ['body' => $body, 'ref' => $default];
        }

        if (($body = self::fetchReadmeForRef($repo, null)) !== null) {
            return ['body' => $body, 'ref' => $default];
        }

        return null;
    }

    private static function fetchReadmeForRef(string $repo, ?string $ref): ?string
    {
        $response = Http::timeout(8)
            ->withHeaders(self::githubHeaders(['Accept' => 'application/vnd.github.raw']))
            ->get("https://api.github.com/repos/{$repo}/readme", $ref ? ['ref' => $ref] : []);

        if ($response->successful()) {
            return $response->body();
        }

        if ($ref === null) {
            return null;
        }

        $raw = Http::timeout(8)
            ->withHeaders(['User-Agent' => 'jeffersongoncalves-site'])
            ->get("https://raw.githubusercontent.com/{$repo}/{$ref}/README.md");

        return $raw->successful() ? $raw->body() : null;
    }

    private static function fetchDefaultBranch(string $repo): ?string
    {
        return Cache::remember(
            "github.default_branch.{$repo}",
            now()->addHours(24),
            function () use ($repo) {
                $response = Http::timeout(8)
                    ->withHeaders(self::githubHeaders(['Accept' => 'application/vnd.github+json']))
                    ->get("https://api.github.com/repos/{$repo}");

                if (! $response->successful()) {
                    return null;
                }

                return $response->json('default_branch');
            }
        );
    }

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
        $base   = "https://raw.githubusercontent.com/{$repo}/{$branch}/";

        return preg_replace_callback(
            '~(!\[[^\]]*\]\()([^)]+)(\))~',
            function ($m) use ($base) {
                $src = trim($m[2]);

                if (preg_match('#^(https?://|data:|/)#i', $src)) {
                    return $m[0];
                }

                return $m[1] . $base . ltrim($src, './') . $m[3];
            },
            $markdown
        );
    }

    private static function renderMarkdown(string $markdown): string
    {
        $environment = new Environment([
            'html_input'         => 'allow',
            'allow_unsafe_links' => false,
            'heading_permalink'  => [
                'symbol'   => '#',
                'html_class' => 'md-anchor',
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new HeadingPermalinkExtension);

        return (new MarkdownConverter($environment))->convert($markdown)->getContent();
    }
}

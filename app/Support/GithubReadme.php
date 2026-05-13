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
            $markdown = self::fetchMarkdown($repo, $ref);

            if ($markdown === null) {
                return null;
            }

            $markdown = self::rewriteRelativeAssets($markdown, $repo, $ref);

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

    private static function fetchMarkdown(string $repo, ?string $ref = null): ?string
    {
        $token   = config('services.github.token');
        $headers = ['Accept' => 'application/vnd.github.raw', 'User-Agent' => 'jeffersongoncalves-site'];

        if ($token) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        $url   = "https://api.github.com/repos/{$repo}/readme";
        $query = $ref ? ['ref' => $ref] : [];

        $response = Http::timeout(8)
            ->withHeaders($headers)
            ->get($url, $query);

        if ($response->successful()) {
            return $response->body();
        }

        $branches = $ref ? [$ref] : ['main', 'master'];

        foreach ($branches as $branch) {
            $raw = Http::timeout(8)->get("https://raw.githubusercontent.com/{$repo}/{$branch}/README.md");
            if ($raw->successful()) {
                return $raw->body();
            }
        }

        return null;
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

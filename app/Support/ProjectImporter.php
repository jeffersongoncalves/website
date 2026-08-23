<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ProjectLanguage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JeffersonGoncalves\GitHubClient\Exceptions\GitHubRateLimitException;
use JeffersonGoncalves\GitHubClient\GitHubClient;
use JeffersonGoncalves\SsrfGuard\SsrfGuard;
use JeffersonGoncalves\TopicNormalizer\TopicNormalizer;
use Throwable;

class ProjectImporter
{
    /**
     * Matches an npmjs.com/package URL, capturing the package id (`name` or
     * `@scope/name`). Shared by every npm-URL parser so the pattern lives once.
     */
    public const NPM_PACKAGE_PATTERN = '~npmjs\.com/package/(@[^/?#]+/[^/?#]+|[^/?#]+)~i';

    /**
     * Fetch a GitHub repo + composer.json + package.json + branches and
     * return a flat array of form fields plus warnings the caller should
     * surface to the editor. Result is cached for an hour per repo so
     * re-opening the modal during a single edit session doesn't hammer the
     * API. Returns `['error' => '<key>']` on any unrecoverable failure.
     *
     * @return array{fields?: array<string, mixed>, warnings?: list<string>, error?: string}
     *
     * @throws GitHubRateLimitException when GitHub is rate-limiting the API
     */
    /**
     * Cache an importer result for an hour, but ONLY when it succeeded
     * (contains 'fields'). Error results (transient fetch/registry failures)
     * are returned but never cached, so a momentary outage doesn't freeze the
     * import as a dead result for the whole TTL.
     *
     * @param  callable():array<string, mixed>  $build
     * @return array<string, mixed>
     */
    private static function cacheSuccessful(string $key, callable $build): array
    {
        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $result = $build();

        if (isset($result['fields'])) {
            Cache::put($key, $result, now()->addHour());
        }

        return $result;
    }

    /**
     * @return array{fields?: array<string, mixed>, warnings?: list<string>, error?: string}
     */
    public static function fromGithub(string $url): array
    {
        $repoSlug = GithubReadme::repoFromUrl($url);

        if (! $repoSlug) {
            return ['error' => 'invalid_url'];
        }

        return self::cacheSuccessful(
            "project_importer:github:{$repoSlug}",
            fn () => self::buildResult($repoSlug, $url)
        );
    }

    /**
     * Fetch a generic website and extract metadata from its <head> tags.
     * Use for projects with no public GitHub repo (SaaS tools, hosted
     * services, etc). Only fills name/title/docs_url + sane defaults
     * for the form's required selects.
     *
     * @return array{fields?: array<string, mixed>, warnings?: list<string>, error?: string}
     */
    public static function fromUrl(string $url): array
    {
        $url = trim($url);

        if (! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $url)) {
            return ['error' => 'invalid_url'];
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return ['error' => 'invalid_url'];
        }

        return self::cacheSuccessful(
            'project_importer:url:'.sha1($url),
            fn () => self::buildUrlResult($url, $host)
        );
    }

    /**
     * Fetch a YouTube channel page and pull its display title + description
     * from the page's Open Graph tags. Forces the Website-style flow with
     * category set to `youtube_channel`. Accepts handle URLs (`/@name`),
     * channel-id URLs (`/channel/UC...`), legacy `/c/name` and `/user/name`.
     * Cached for an hour per URL. Returns `['error' => '<key>']` on failure.
     *
     * @return array{fields?: array<string, mixed>, warnings?: list<string>, error?: string}
     */
    public static function fromYoutube(string $url): array
    {
        $url = trim($url);

        if (! preg_match('~^https?://(?:www\.)?youtube\.com/(@[^/?#]+|channel/[^/?#]+|c/[^/?#]+|user/[^/?#]+)~i', $url, $m)) {
            return ['error' => 'invalid_url'];
        }

        $pathSegment = $m[1];

        return self::cacheSuccessful(
            'project_importer:youtube:'.sha1($url),
            fn () => self::buildYoutubeResult($url, $pathSegment)
        );
    }

    /**
     * Fetch a single article/blog-post URL and map its <head> metadata to form
     * fields under the `article` category. Unlike fromUrl (which represents a
     * whole site and names the row after the host), this names the row after
     * the post title and keeps the deep link in docs_url. Cached for an hour
     * per URL. Returns `['error' => '<key>']` on failure.
     *
     * @return array{fields?: array<string, mixed>, warnings?: list<string>, error?: string}
     */
    public static function fromArticle(string $url): array
    {
        $url = trim($url);

        if (! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $url)) {
            return ['error' => 'invalid_url'];
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return ['error' => 'invalid_url'];
        }

        return self::cacheSuccessful(
            'project_importer:article:'.sha1($url),
            fn () => self::buildArticleResult($url, $host)
        );
    }

    /**
     * @return array{fields?: array<string, mixed>, warnings?: list<string>, error?: string}
     */
    private static function buildArticleResult(string $url, string $host): array
    {
        $html = self::fetchPageHtml($url, 'article');

        if ($html === null) {
            return ['error' => 'fetch_failed'];
        }

        $meta = self::parseMeta($html);

        $rawTitle = $meta['og:title'] ?? $meta['title'] ?? null;
        $title = is_string($rawTitle) && trim($rawTitle) !== ''
            ? trim($rawTitle)
            : self::nameFromHost($host);
        $description = self::nullableString($meta['og:description'] ?? $meta['description'] ?? null);

        // Use the article's own publish date (og `article:published_time`) so the
        // schema.org datePublished reflects when the post actually went live,
        // not when it was imported here. Stored as an ISO string — the model's
        // datetime cast and the Filament DateTimePicker both parse it.
        $publishedAt = self::parseMetaDate($meta['article:published_time'] ?? null);

        $warnings = [];

        $fields = [
            'github_url' => null,
            'slug' => self::articleSlugFromUrl($url),
            'name' => $title,
            'repo' => null,
            'license' => null,
            'readme_branch' => null,
            'docs_url' => $url,
            ...self::titlesFromDescription($description, $title, $warnings),
            'category' => 'article',
            'package_type' => 'none',
            'packagist_url' => null,
            'npm_url' => null,
            'social_image' => self::nullableString($meta['og:image'] ?? null),
            'published_at' => $publishedAt,
            'stack' => [],
            'versions' => [],
        ];

        if ($publishedAt === null) {
            // Editor should set the real date by hand — the importer leaves it
            // blank and the observer falls back to stamping "now" on publish.
            $warnings[] = 'no_published_date';
        }

        return ['fields' => $fields, 'warnings' => $warnings];
    }

    /**
     * Article slug derived from the post's own last path segment when present
     * (`/blog/automate-your-php-security-updates` → that segment), prefixed
     * `article-` to stay visually distinct and collision-safe. Falls back to
     * the host when the URL has no path.
     */
    public static function articleSlugFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $segments = array_values(array_filter(explode('/', $path), fn (string $s): bool => $s !== ''));
        $last = end($segments);

        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $host = preg_replace('/^www\./i', '', $host) ?? $host;

        // array_filter already dropped empty segments, so a string $last is
        // non-empty; end() only yields false when there were no segments.
        $basis = is_string($last) ? $last : $host;

        // Map dots to hyphens first (mirrors siteSlugFromUrl) so a host
        // fallback reads example-com, not examplecom.
        return 'article-'.Str::slug(str_replace('.', '-', $basis));
    }

    /**
     * Fetch an npm package from the public registry and map its manifest to
     * form fields. Mirrors fromGithub's shape: name/description/license come
     * from the registry document, github_url is recovered from the
     * `repository` field and docs_url from `homepage`. Cached for an hour per
     * package. Returns `['error' => '<key>']` on failure.
     *
     * @return array{fields?: array<string, mixed>, warnings?: list<string>, error?: string}
     *
     * @throws GitHubRateLimitException when recovering the GitHub repo hits a rate limit
     */
    public static function fromNpm(string $url): array
    {
        $package = self::npmNameFromUrl($url);

        if ($package === null) {
            return ['error' => 'invalid_url'];
        }

        return self::cacheSuccessful(
            "project_importer:npm:{$package}",
            fn () => self::buildNpmResult($package)
        );
    }

    /**
     * @return array{fields?: array<string, mixed>, warnings?: list<string>, error?: string}
     */
    private static function buildNpmResult(string $package): array
    {
        $data = self::fetchNpmRegistry($package);

        if ($data === null) {
            return ['error' => 'repo_not_found'];
        }

        $name = is_string($data['name'] ?? null) ? $data['name'] : $package;
        $description = self::nullableString($data['description'] ?? null);
        $homepage = self::nullableString($data['homepage'] ?? null);
        $license = self::npmLicense($data['license'] ?? null);

        $repoInfo = self::parseGithubRepository($data['repository'] ?? null);
        $githubUrl = null;
        $missingDirectoryReadme = false;

        if ($repoInfo !== null) {
            $githubUrl = 'https://github.com/'.$repoInfo['slug'];

            if ($repoInfo['directory'] !== null) {
                $branch = GitHubClient::fetchDefaultBranchForSlug($repoInfo['slug']) ?? 'main';
                $githubUrl .= '/tree/'.$branch.'/'.$repoInfo['directory'];
                $missingDirectoryReadme = ! GitHubClient::subdirectoryHasReadme(
                    $repoInfo['slug'],
                    $repoInfo['directory'],
                );
            }
        }

        // Drop docs_url when homepage just points back at the GitHub repo —
        // github_url already covers that, no need to duplicate the link.
        $docsUrl = $homepage;
        if ($docsUrl !== null && $githubUrl !== null && rtrim($docsUrl, '/') === rtrim($githubUrl, '/')) {
            $docsUrl = null;
        }

        $warnings = [];

        $fields = [
            'github_url' => $githubUrl,
            // Strip the `@scope/` punctuation before slugging — Str::slug would
            // otherwise transliterate `@` to "at" (@scope/name → at-scopename).
            'slug' => Str::slug(str_replace(['@', '/'], ['', '-'], $name)),
            'name' => self::prettifyNpmName($name),
            'repo' => null,
            'license' => $license,
            'readme_branch' => null,
            'docs_url' => $docsUrl,
            ...self::titlesFromDescription($description, self::prettifyNpmName($name), $warnings),
            'category' => 'javascript_package',
            'package_type' => 'npm',
            'packagist_url' => null,
            'npm_url' => 'https://www.npmjs.com/package/'.$name,
            'stack' => [],
            'topics' => TopicNormalizer::normalize(is_array($data['keywords'] ?? null) ? $data['keywords'] : []),
            'versions' => [],
        ];

        if ($missingDirectoryReadme) {
            $warnings[] = 'no_directory_readme';
        }

        return ['fields' => $fields, 'warnings' => $warnings];
    }

    /**
     * @return array{fields?: array<string, mixed>, warnings?: list<string>, error?: string}
     */
    private static function buildYoutubeResult(string $url, string $pathSegment): array
    {
        // YouTube replies with a placeholder shell to bare bots — fetchPageHtml's
        // real-browser UA gets the og: tag-rich HTML we need.
        $html = self::fetchPageHtml($url, 'youtube');

        if ($html === null) {
            return ['error' => 'fetch_failed'];
        }

        $meta = self::parseMeta($html);

        $rawTitle = $meta['og:title'] ?? $meta['title'] ?? null;
        $name = self::cleanYoutubeTitle(is_string($rawTitle) ? $rawTitle : null, $pathSegment);
        $description = self::nullableString($meta['og:description'] ?? $meta['description'] ?? null);

        // Use the channel handle / id for the slug — gives stable URLs that
        // don't shift when the user renames the channel.
        $handle = str_starts_with($pathSegment, '@')
            ? substr($pathSegment, 1)
            : (str_contains($pathSegment, '/') ? explode('/', $pathSegment, 2)[1] : $pathSegment);
        $slug = 'youtube-'.Str::slug($handle);

        $warnings = [];

        $fields = [
            'github_url' => null,
            'slug' => $slug,
            'name' => $name,
            'repo' => null,
            'license' => null,
            'readme_branch' => null,
            'docs_url' => $url,
            ...self::titlesFromDescription($description, $name, $warnings),
            'category' => 'youtube_channel',
            'package_type' => 'none',
            'packagist_url' => null,
            'npm_url' => null,
            'stack' => [],
            'versions' => [],
        ];

        return ['fields' => $fields, 'warnings' => $warnings];
    }

    /**
     * Trim YouTube's "- YouTube" suffix from a fetched <title> / og:title and
     * fall back to a prettified handle when the page returns no title at all.
     */
    private static function cleanYoutubeTitle(?string $raw, string $pathSegment): string
    {
        $fallback = self::prettifyName(str_replace(['@', '/', '_'], ['', '-', '-'], $pathSegment));

        if (! is_string($raw) || trim($raw) === '') {
            return $fallback;
        }

        $cleaned = (string) preg_replace('~\s*[-–|]\s*YouTube\s*$~iu', '', $raw);
        $cleaned = trim($cleaned);

        return $cleaned !== '' ? $cleaned : $fallback;
    }

    /**
     * Log an outbound-fetch exception with enough context to tell a timeout /
     * DNS / TLS failure apart from a clean non-2xx response (those return their
     * own error codes without throwing). Keeps the swallow-and-degrade
     * behaviour the callers rely on — this only adds the breadcrumb that was
     * missing, so a failed import is no longer a silent dead end in the log.
     */
    private static function logFetchFailure(string $context, string $target, Throwable $e): void
    {
        Log::warning('ProjectImporter outbound fetch failed', [
            'context' => $context,
            'target' => $target,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }

    /**
     * Extract the package identifier (`name` or `@scope/name`) from an
     * npmjs.com/package URL. Returns null for anything else.
     */
    private static function npmNameFromUrl(string $url): ?string
    {
        if (! preg_match(self::NPM_PACKAGE_PATTERN, trim($url), $m)) {
            return null;
        }

        return rtrim($m[1], '/');
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function fetchNpmRegistry(string $package): ?array
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'jeffersongoncalves-site',
                    'Accept' => 'application/json',
                ])
                ->get('https://registry.npmjs.org/'.$package);
        } catch (Throwable $e) {
            self::logFetchFailure('npm_registry', $package, $e);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Pull the owner/repo slug and optional monorepo subdirectory out of an
     * npm `repository` field, which can be a string remote or
     * `{type, url, directory}`. Returns null when the remote isn't a GitHub
     * one. Lets the caller compose the URL (root vs. /tree/branch/dir) and
     * branch off into extra validation when a subdirectory is declared.
     *
     * @return array{slug:string, directory:?string}|null
     */
    private static function parseGithubRepository(mixed $repository): ?array
    {
        if (is_string($repository)) {
            $raw = $repository;
            $directory = null;
        } elseif (is_array($repository) && is_string($repository['url'] ?? null)) {
            $raw = $repository['url'];
            $directory = is_string($repository['directory'] ?? null)
                ? trim($repository['directory'], '/')
                : null;
            if ($directory === '') {
                $directory = null;
            }
        } else {
            return null;
        }

        if (! preg_match('~github\.com[/:]([^/]+/[^/?#]+?)(?:\.git)?(?:[/?#]|$)~i', $raw, $m)) {
            return null;
        }

        return ['slug' => $m[1], 'directory' => $directory];
    }

    /**
     * Normalise the npm `license` field, which is a SPDX string on modern
     * packages but a legacy `{type, url}` object on older ones.
     */
    private static function npmLicense(mixed $license): ?string
    {
        if (is_string($license)) {
            return self::nullableString($license);
        }

        if (is_array($license) && is_string($license['type'] ?? null)) {
            return self::nullableString($license['type']);
        }

        return null;
    }

    /**
     * Prettify an npm package name for display. Scoped packages keep their
     * scope so a generic unscoped name stays meaningful: `@tailwindcss/vite`
     * → "Tailwindcss Vite" rather than a bare "Vite".
     */
    private static function prettifyNpmName(string $name): string
    {
        if (str_starts_with($name, '@') && str_contains($name, '/')) {
            return self::prettifyName(str_replace('/', '-', ltrim($name, '@')));
        }

        return self::prettifyName($name);
    }

    /**
     * @return array{fields?: array<string, mixed>, warnings?: list<string>, error?: string}
     */
    private static function buildUrlResult(string $url, string $host): array
    {
        $html = self::fetchPageHtml($url, 'website');

        if ($html === null) {
            return ['error' => 'fetch_failed'];
        }

        $meta = self::parseMeta($html);

        $name = self::nameFromHost($host);
        $description = $meta['og:description'] ?? $meta['description'] ?? null;
        $title = $meta['og:title'] ?? $meta['title'] ?? $name;

        $warnings = [];

        $fields = [
            'github_url' => null,
            'slug' => self::siteSlugFromUrl($url),
            'name' => $name,
            'repo' => null,
            'license' => null,
            'readme_branch' => null,
            'docs_url' => $url,
            ...self::titlesFromDescription($description, $title, $warnings),
            // URL imports always represent external sites (blogs, hosted
            // services, personal pages) — there's no manifest to classify
            // against, so default to the Website category instead of the
            // generic "tool" fallback used for GitHub imports.
            'category' => 'website',
            'package_type' => 'none',
            'packagist_url' => null,
            'npm_url' => null,
            'social_image' => self::nullableString($meta['og:image'] ?? null),
            'stack' => [],
            'versions' => [],
        ];

        return ['fields' => $fields, 'warnings' => $warnings];
    }

    /**
     * Fetch an HTML page for OpenGraph scraping. Tries a real-browser UA first
     * (what most sites expect), then falls back to a social link-preview crawler
     * UA for hosts that 403 datacenter browser requests but still serve og: meta
     * to known preview bots (e.g. Medium). Returns the body, or null on a network
     * error or when every attempt comes back non-2xx.
     */
    /**
     * Whether $url is a plain http(s) URL whose host resolves only to public
     * IPs (deny-by-default). Mirrors OgImageController's SSRF guard. Bypassed
     * under tests, which use non-resolving fake hosts (blog.test, etc) with
     * Http::fake — real DNS lookups there would reject every fixture.
     */
    private static function isPublicHttpUrl(string $url): bool
    {
        if (app()->runningUnitTests()) {
            return true;
        }

        return app(SsrfGuard::class)->isPublicUrl($url);
    }

    private static function fetchPageHtml(string $url, string $context): ?string
    {
        // The link-importer fetches caller-supplied URLs server-side, so guard
        // against SSRF: reject hosts that resolve to private/reserved ranges and
        // re-validate every redirect hop (a public host could 302 to metadata).
        if (! self::isPublicHttpUrl($url)) {
            logger()->warning('ProjectImporter: refused non-public fetch URL', ['context' => $context, 'url' => $url]);

            return null;
        }

        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
            'Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)',
        ];

        foreach ($userAgents as $userAgent) {
            try {
                $response = Http::timeout(8)
                    ->withOptions(['allow_redirects' => [
                        'max' => 5,
                        'on_redirect' => function ($request, $response, $uri): void {
                            if (! self::isPublicHttpUrl((string) $uri)) {
                                throw new \RuntimeException('import fetch redirect to non-public host blocked: '.$uri);
                            }
                        },
                    ]])
                    ->withHeaders([
                        'User-Agent' => $userAgent,
                        'Accept' => 'text/html,application/xhtml+xml',
                        'Accept-Language' => 'en-US,en;q=0.9',
                    ])
                    ->get($url);
            } catch (Throwable $e) {
                // A network error (timeout/DNS/TLS) won't be fixed by a
                // different UA — bail instead of retrying.
                self::logFetchFailure($context, $url, $e);

                return null;
            }

            if ($response->successful()) {
                return $response->body();
            }
        }

        // Every UA came back non-2xx (e.g. a hard 403/404).
        return null;
    }

    /**
     * Best-effort meta extraction from an HTML <head>. Reads <title>, the
     * standard <meta name="description">, and the Open Graph counterparts.
     * Silently ignores libxml warnings on malformed markup.
     *
     * @return array<string, string>
     */
    private static function parseMeta(string $html): array
    {
        $meta = [];

        $previous = libxml_use_internal_errors(true);

        $doc = new \DOMDocument;
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html);

        $titleNodes = $doc->getElementsByTagName('title');
        if ($titleNodes->length > 0) {
            $value = trim((string) $titleNodes->item(0)?->textContent);
            if ($value !== '') {
                $meta['title'] = $value;
            }
        }

        foreach ($doc->getElementsByTagName('meta') as $node) {
            $property = $node->getAttribute('property') ?: $node->getAttribute('name');
            $content = trim($node->getAttribute('content'));
            if ($property === '' || $content === '') {
                continue;
            }
            $key = strtolower($property);
            if (in_array($key, ['title', 'description', 'og:title', 'og:description', 'og:image', 'article:published_time', 'article:modified_time'], true)) {
                $meta[$key] = $content;
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $meta;
    }

    /**
     * Parse an OpenGraph article date (`article:published_time`, ISO 8601) into a
     * normalised ISO string, or null when absent / unparseable / implausible (a
     * future date or one before the web existed is treated as garbage).
     */
    private static function parseMetaDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = Carbon::parse(trim($value));
        } catch (Throwable $e) {
            Log::warning('ProjectImporter parseMetaDate failed', [
                'value' => $value,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($date->isFuture() || $date->year < 1995) {
            return null;
        }

        return $date->toIso8601String();
    }

    private static function nameFromHost(string $host): string
    {
        $host = preg_replace('/^www\./i', '', $host) ?? $host;
        $first = explode('.', $host)[0];

        return ucfirst($first);
    }

    /**
     * Canonical site slug derived from a URL: `site-<host>[-<path>]`.
     * The `site-` prefix keeps website rows visually distinct from
     * github/composer/npm imports, and the path suffix prevents collisions
     * when several entries share the same host (e.g. `laravel.com/` vs
     * `laravel.com/docs/master/homestead`).
     */
    public static function siteSlugFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $host = preg_replace('/^www\./i', '', $host) ?? $host;
        $hostSlug = strtolower(str_replace('.', '-', $host));

        if ($hostSlug === '') {
            return '';
        }

        $slug = 'site-'.$hostSlug;

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $path = trim($path, '/');
        if ($path !== '') {
            // Str::slug strips slashes instead of treating them as separators,
            // so collapse path segments to hyphens before slugifying.
            $slug .= '-'.Str::slug(str_replace('/', '-', $path));
        }

        return $slug;
    }

    /**
     * @return array{fields?: array<string, mixed>, warnings?: list<string>, error?: string}
     */
    private static function buildResult(string $repoSlug, string $url): array
    {
        $repo = GitHubClient::fetchRepo($repoSlug);

        if ($repo === null) {
            return ['error' => 'repo_not_found'];
        }

        $branch = is_string($repo['default_branch'] ?? null) ? $repo['default_branch'] : 'main';
        $composer = GitHubClient::fetchManifest($repoSlug, $branch, 'composer.json');
        $package = GitHubClient::fetchManifest($repoSlug, $branch, 'package.json');
        // Docker classification signals — checked on root + the two common
        // self-hosted layouts (plausible/analytics ships `hosting/`, several
        // others ship `installer/`). HEAD against raw.githubusercontent.com
        // so we don't pull the file contents.
        $composePaths = [
            'docker-compose.yml',
            'docker-compose.yaml',
            'compose.yml',
            'compose.yaml',
            'hosting/docker-compose.yml',
            'hosting/docker-compose.yaml',
            'installer/docker-compose.yml',
            'installer/docker-compose.yaml',
            'docker/docker-compose.yml',
            'docker/docker-compose.yaml',
        ];
        $hasDockerCompose = false;
        foreach ($composePaths as $path) {
            if (GitHubClient::fileExists($repoSlug, $branch, $path)) {
                $hasDockerCompose = true;
                break;
            }
        }
        // Standalone Dockerfile counts as a Docker signal only when the
        // repo doesn't ship composer.json / package.json — otherwise a
        // Laravel app with a dev Dockerfile would be misclassified.
        $hasStandaloneDockerfile = $composer === null
            && $package === null
            && GitHubClient::fileExists($repoSlug, $branch, 'Dockerfile');
        $branches = GitHubClient::fetchBranches($repoSlug);

        [$owner, $repoName] = explode('/', $repoSlug, 2);

        $category = ProjectClassifier::category($composer, $repo, $hasDockerCompose || $hasStandaloneDockerfile);

        // Only treat the project as an npm package if it's actually published
        // *and* the registry's repository field points back at this github
        // url. A `package.json` checked into the repo is not enough — many
        // repos ship one for tooling (eslint, vite, prettier) without ever
        // pushing to the registry, and others ship a misattributed `name`
        // (e.g. mpvue ships `"name": "vue"`) that would otherwise claim a
        // foreign package as their own.
        $npmName = self::extractNpmName($package);
        $npmPublished = $npmName !== null
            && self::npmPackageExists($npmName)
            && self::npmPackageBelongsToRepo($npmName, $url);

        // A composer.json `name` alone is not proof the package is published as
        // that vendor/name — app skeletons and forks ship `"name": "laravel/laravel"`
        // (or similar) without owning the package. Resolve the tri-state Packagist
        // ownership once: only a definitive OWNED advertises packagist_url, and a
        // definitive FOREIGN means the repo is an application, not a package.
        $packagistName = self::packagistNameFromComposer($composer);
        $packagistStatus = $packagistName !== null
            ? self::packagistOwnershipForName($packagistName, $url)
            : null;
        $packagistOwned = $packagistStatus === self::LINK_OWNED;

        // A checked-in composer.json only makes the repo a Composer *package* when
        // it actually owns the published package. App skeletons / tutorials with a
        // borrowed `laravel/laravel` name are applications — type them `none` so no
        // packagist link or download count is ever derived (the metrics sync gates
        // packagist re-derivation on package_type === composer). A transient UNKNOWN
        // (rate limit) keeps `composer` so a genuine package isn't downgraded
        // mid-bulk-import; the cleanup job re-verifies later.
        $packageType = self::resolvePackageType(
            $composer,
            $package,
            $repo,
            $npmPublished,
            $packagistStatus !== self::LINK_FOREIGN,
        );
        // Docker-distributed projects don't fit any of the package-manager
        // types resolvePackageType knows about, but they still ship as an
        // image — flip the type so the badge + downloads tracker uses the
        // Docker rail instead of falling back to `none`.
        if ($category === 'docker' && $packageType === 'none') {
            $packageType = 'docker';
        }
        // Generic GitHub imports that fell through to the `tool` fallback are
        // split by what the repo actually ships: a Composer manifest makes it
        // a PHP package, a published npm package a JavaScript one, and anything
        // else a standalone application. `tool` is reserved for CLI utilities
        // the editor reclassifies by hand.
        if ($category === 'tool') {
            $category = match ($packageType) {
                'composer' => 'php_package',
                'npm' => 'javascript_package',
                default => 'application',
            };
        }
        $description = self::pickDescription($composer, $package, $repo);

        $warnings = [];

        if ($category === 'application') {
            $warnings[] = 'category_fallback';
        }

        $fields = [
            'github_url' => $url,
            'slug' => Str::slug($owner.'-'.$repoName),
            'name' => self::prettifyName($repoName),
            'repo' => $repoName,
            'license' => self::normalizeLicense($repo['license'] ?? null),
            'readme_branch' => $branch,
            'docs_url' => self::nullableString($repo['homepage'] ?? null),
            ...self::titlesFromDescription($description, self::prettifyName($repoName), $warnings),
            'category' => $category,
            'package_type' => $packageType,
            'language' => ProjectLanguage::tryFrom((string) ($repo['language'] ?? ''))?->value,
            'packagist_url' => $packagistOwned ? 'https://packagist.org/packages/'.$packagistName : null,
            'npm_url' => $npmPublished ? 'https://www.npmjs.com/package/'.$npmName : null,
            'stack' => ProjectClassifier::stack($composer, $package),
            'topics' => TopicNormalizer::normalize(
                is_array($repo['topics'] ?? null) ? $repo['topics'] : [],
                is_array($composer['keywords'] ?? null) ? $composer['keywords'] : [],
                is_array($package['keywords'] ?? null) ? $package['keywords'] : [],
            ),
            'versions' => ProjectClassifier::versions($composer, $branches, $category),
            'has_branches' => ProjectClassifier::hasVersionBranches($branches, $category),
        ];

        if ($npmName !== null && ! $npmPublished) {
            $warnings[] = 'npm_not_published';
        }

        if ($packagistName !== null && ! $packagistOwned) {
            $warnings[] = 'packagist_not_owned';
        }

        return ['fields' => $fields, 'warnings' => $warnings];
    }

    /**
     * @param  array<string, mixed>|null  $composer
     * @param  array<string, mixed>|null  $package
     * @param  array<string, mixed>  $repo
     */
    private static function resolvePackageType(?array $composer, ?array $package, array $repo, bool $npmPublished, bool $composerOwned = true): string
    {
        // Skeletons/tutorials that ship a borrowed composer name (definitively
        // FOREIGN on Packagist) are applications, not Composer packages.
        if ($composer !== null && $composerOwned) {
            return 'composer';
        }

        if ($package !== null && $npmPublished) {
            return 'npm';
        }

        $homepage = is_string($repo['homepage'] ?? null) ? (string) $repo['homepage'] : '';
        if ($homepage !== '' && str_contains($homepage, 'plugins.jetbrains.com')) {
            return 'jetbrains';
        }

        return 'none';
    }

    /**
     * Pull the package name out of a parsed `package.json`, if it's a valid
     * npm identifier. Used to gate the registry lookup so we don't ping
     * npmjs.org for repos that don't ship a package.json at all.
     *
     * @param  array<string, mixed>|null  $package
     */
    private static function extractNpmName(?array $package): ?string
    {
        $name = $package['name'] ?? null;

        if (! is_string($name) || ! preg_match('#^(@[a-z0-9_.~-]+/)?[a-z0-9_.~-]+$#i', $name)) {
            return null;
        }

        return $name;
    }

    /**
     * HEAD the public npm registry to verify a package was actually
     * published under this name. Hitting the JSON GET would also work but
     * HEAD is cheaper and the body isn't needed — we only care about the
     * 200 vs 404 distinction. Network failures degrade to "not published"
     * so a flaky registry doesn't fake a package_type=npm.
     */
    private static function npmPackageExists(string $name): bool
    {
        // Scoped packages keep the slash unencoded in the registry URL.
        $url = 'https://registry.npmjs.org/'.$name;

        try {
            $response = Http::timeout(6)
                ->withHeaders([
                    'User-Agent' => 'jeffersongoncalves-site',
                    'Accept' => 'application/json',
                ])
                ->head($url);
        } catch (Throwable $e) {
            self::logFetchFailure('npm_head', $url, $e);

            return false;
        }

        return $response->successful();
    }

    /**
     * Confirm the registry's `repository` field for `$name` points at the
     * same owner/repo as `$expectedGithubUrl`. Guards against misattributed
     * package.json `name` fields (e.g. mpvue ships `"name": "vue"`, which
     * would otherwise advertise the real vue npm package as mpvue's own).
     * Only a definitive `owned` is trusted.
     */
    private static function npmPackageBelongsToRepo(string $name, string $expectedGithubUrl): bool
    {
        return self::npmOwnershipForName($name, $expectedGithubUrl) === self::LINK_OWNED;
    }

    /**
     * Tri-state npm ownership of `$name` relative to `$expectedGithubUrl`. Same
     * OWNED/FOREIGN/UNKNOWN semantics as packagistOwnershipForName — the cleanup
     * job must not treat a transient UNKNOWN as FOREIGN.
     */
    private static function npmOwnershipForName(string $name, string $expectedGithubUrl): string
    {
        $expectedSlug = GithubReadme::repoFromUrl($expectedGithubUrl);

        if ($expectedSlug === null) {
            return self::LINK_UNKNOWN;
        }

        try {
            $response = Http::timeout(6)
                ->withHeaders([
                    'User-Agent' => 'jeffersongoncalves-site',
                    'Accept' => 'application/json',
                ])
                ->get('https://registry.npmjs.org/'.$name);
        } catch (Throwable $e) {
            self::logFetchFailure('npm_ownership', $name, $e);

            return self::LINK_UNKNOWN;
        }

        // 404 = the package genuinely isn't published → safe to call foreign.
        if ($response->status() === 404) {
            return self::LINK_FOREIGN;
        }

        if (! $response->successful()) {
            return self::LINK_UNKNOWN;
        }

        $data = $response->json();
        $repositoryUrl = null;

        if (is_array($data) && isset($data['repository'])) {
            $repo = $data['repository'];
            if (is_string($repo)) {
                $repositoryUrl = $repo;
            } elseif (is_array($repo) && isset($repo['url']) && is_string($repo['url'])) {
                $repositoryUrl = $repo['url'];
            }
        }

        if ($repositoryUrl === null) {
            return self::LINK_UNKNOWN;
        }

        return self::sameGithubOwner(GithubReadme::repoFromUrl($repositoryUrl), $expectedSlug)
            ? self::LINK_OWNED
            : self::LINK_FOREIGN;
    }

    /**
     * Public tri-state check for a stored `npm_url` against a project's
     * `github_url` — used by the cleanup job. A malformed npm_url is UNKNOWN
     * (never purge what we can't parse).
     */
    public static function npmUrlOwnershipStatus(string $npmUrl, string $githubUrl): string
    {
        if (! preg_match(self::NPM_PACKAGE_PATTERN, $npmUrl, $m)) {
            return self::LINK_UNKNOWN;
        }

        return self::npmOwnershipForName(rtrim($m[1], '/'), $githubUrl);
    }

    /**
     * @param  array<string, mixed>|null  $composer
     * @param  array<string, mixed>|null  $package
     * @param  array<string, mixed>  $repo
     */
    private static function pickDescription(?array $composer, ?array $package, array $repo): ?string
    {
        foreach ([$composer['description'] ?? null, $package['description'] ?? null, $repo['description'] ?? null] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    /**
     * The normalised `vendor/name` from a composer.json, or null when absent /
     * malformed. Does NOT prove the package is published — callers must verify
     * ownership with packagistOwnershipForName before trusting it.
     *
     * @param  array<string, mixed>|null  $composer
     */
    private static function packagistNameFromComposer(?array $composer): ?string
    {
        $name = $composer['name'] ?? null;

        if (! is_string($name) || ! preg_match('#^[a-z0-9_.-]+/[a-z0-9_.-]+$#i', $name)) {
            return null;
        }

        return strtolower($name);
    }

    /** The registry's `repository` resolves to the imported repo. */
    public const LINK_OWNED = 'owned';

    /** Package is unpublished (404) or owned by a different repo. */
    public const LINK_FOREIGN = 'foreign';

    /** Couldn't verify — network error, rate limit (429/5xx), or no repository. */
    public const LINK_UNKNOWN = 'unknown';

    /**
     * Tri-state ownership of `$name` relative to `$expectedGithubUrl`:
     *  - OWNED   — Packagist `repository` matches the repo.
     *  - FOREIGN — a 404 (unpublished) or a repository pointing elsewhere.
     *  - UNKNOWN — network failure, rate limit (429/5xx), or missing repository.
     *
     * The distinction matters for the cleanup job: a transient UNKNOWN must NOT
     * be treated as FOREIGN, or a Packagist rate-limit would nuke valid links.
     */
    private static function packagistOwnershipForName(string $name, string $expectedGithubUrl): string
    {
        $expectedSlug = GithubReadme::repoFromUrl($expectedGithubUrl);

        if ($expectedSlug === null) {
            return self::LINK_UNKNOWN;
        }

        try {
            $response = Http::timeout(6)
                ->withHeaders([
                    'User-Agent' => 'jeffersongoncalves-site',
                    'Accept' => 'application/json',
                ])
                ->get("https://packagist.org/packages/{$name}.json");
        } catch (Throwable $e) {
            self::logFetchFailure('packagist_ownership', $name, $e);

            return self::LINK_UNKNOWN;
        }

        // 404 = the package genuinely isn't published → safe to call foreign.
        if ($response->status() === 404) {
            return self::LINK_FOREIGN;
        }

        // Anything else non-2xx (429 rate limit, 5xx) is inconclusive.
        if (! $response->successful()) {
            return self::LINK_UNKNOWN;
        }

        $data = $response->json();
        $repositoryUrl = is_array($data) && isset($data['package']['repository']) && is_string($data['package']['repository'])
            ? $data['package']['repository']
            : null;

        if ($repositoryUrl === null) {
            return self::LINK_UNKNOWN;
        }

        return self::sameGithubOwner(GithubReadme::repoFromUrl($repositoryUrl), $expectedSlug)
            ? self::LINK_OWNED
            : self::LINK_FOREIGN;
    }

    /**
     * Two repo slugs (`owner/repo`) belong to the same project when they share
     * the GitHub OWNER, not necessarily the exact repo. Monorepos split-publish
     * to per-package read-only repos under the same org — e.g. the github repo
     * filamentphp/filament's `filament/filament` package lists its repository as
     * filamentphp/panels. Same owner (filamentphp) → ours. A borrowed name from
     * a different org (savanihd shipping laravel/laravel) → different owner → not.
     */
    private static function sameGithubOwner(?string $slugA, ?string $slugB): bool
    {
        if ($slugA === null || $slugB === null) {
            return false;
        }

        $ownerA = strtolower(explode('/', $slugA, 2)[0]);
        $ownerB = strtolower(explode('/', $slugB, 2)[0]);

        return $ownerA !== '' && $ownerA === $ownerB;
    }

    /**
     * Public tri-state check for an already-stored `packagist_url` against a
     * project's `github_url` — used by the cleanup job. Returns one of the
     * LINK_* constants. A malformed packagist_url is UNKNOWN (never purge on
     * something we can't even parse).
     */
    public static function packagistUrlOwnershipStatus(string $packagistUrl, string $githubUrl): string
    {
        if (! preg_match('~packagist\.org/packages/([a-z0-9_.-]+/[a-z0-9_.-]+)~i', $packagistUrl, $m)) {
            return self::LINK_UNKNOWN;
        }

        return self::packagistOwnershipForName(strtolower($m[1]), $githubUrl);
    }

    /**
     * Mirror a single description across all three locale title fields — the
     * importer can't translate, so the editor edits per-locale later; the same
     * value beats null fields it would have to clear. Falls back to `$fallback`
     * when there's no description so the title never imports blank, and pushes a
     * `no_description` warning in that case.
     *
     * @param  list<string>  $warnings
     * @return array{'title.en': string, 'title.pt': string, 'title.es': string}
     */
    private static function titlesFromDescription(?string $description, string $fallback, array &$warnings): array
    {
        $value = $description ?? $fallback;

        if ($description === null) {
            $warnings[] = 'no_description';
        }

        return [
            'title.en' => $value,
            'title.pt' => $value,
            'title.es' => $value,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Map GitHub's `license` object to a stored license string. GitHub returns
     * spdx_id `NOASSERTION` (name "Other") when a repo ships a LICENSE file it
     * can't match to an SPDX id — store the human "Other" label instead of the
     * opaque token. Repos with no license object at all keep the MIT default.
     *
     * @param  array<string, mixed>|null  $license
     */
    private static function normalizeLicense(?array $license): string
    {
        $spdx = is_string($license['spdx_id'] ?? null) ? $license['spdx_id'] : null;

        if ($spdx === null) {
            return 'MIT';
        }

        if (in_array($spdx, ['NOASSERTION', 'NONE'], true)) {
            return 'Other';
        }

        return $spdx;
    }

    private static function prettifyName(string $repo): string
    {
        $name = ucwords(str_replace(['-', '_'], ' ', $repo));

        return preg_replace('/\bPhp\b/u', 'PHP', $name) ?? $name;
    }
}

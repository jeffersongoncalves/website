<?php

namespace App\Support;

use App\Enums\ProjectLanguage;
use App\Exceptions\GithubRateLimitException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProjectImporter
{
    /**
     * Fetch a GitHub repo + composer.json + package.json + branches and
     * return a flat array of form fields plus warnings the caller should
     * surface to the editor. Result is cached for an hour per repo so
     * re-opening the modal during a single edit session doesn't hammer the
     * API. Returns `['error' => '<key>']` on any unrecoverable failure.
     *
     * @return array{fields?: array<string, mixed>, warnings?: list<string>, error?: string}
     *
     * @throws GithubRateLimitException when GitHub is rate-limiting the API
     */
    public static function fromGithub(string $url): array
    {
        $repoSlug = GithubReadme::repoFromUrl($url);

        if (! $repoSlug) {
            return ['error' => 'invalid_url'];
        }

        return Cache::remember(
            "project_importer:github:{$repoSlug}",
            now()->addHour(),
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

        return Cache::remember(
            'project_importer:url:'.sha1($url),
            now()->addHour(),
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

        return Cache::remember(
            'project_importer:youtube:'.sha1($url),
            now()->addHour(),
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

        return Cache::remember(
            'project_importer:article:'.sha1($url),
            now()->addHour(),
            fn () => self::buildArticleResult($url, $host)
        );
    }

    /**
     * @return array{fields?: array<string, mixed>, warnings?: list<string>, error?: string}
     */
    private static function buildArticleResult(string $url, string $host): array
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                ->get($url);
        } catch (Throwable $e) {
            self::logFetchFailure('article', $url, $e);

            return ['error' => 'fetch_failed'];
        }

        if (! $response->successful()) {
            return ['error' => 'fetch_failed'];
        }

        $meta = self::parseMeta($response->body());

        $rawTitle = $meta['og:title'] ?? $meta['title'] ?? null;
        $title = is_string($rawTitle) && trim($rawTitle) !== ''
            ? trim($rawTitle)
            : self::nameFromHost($host);
        $description = self::nullableString($meta['og:description'] ?? $meta['description'] ?? null);

        $fields = [
            'github_url' => null,
            'slug' => self::articleSlugFromUrl($url),
            'name' => $title,
            'repo' => null,
            'license' => null,
            'readme_branch' => null,
            'docs_url' => $url,
            'title.en' => $description ?? $title,
            'title.pt' => $description ?? $title,
            'title.es' => $description ?? $title,
            'category' => 'article',
            'package_type' => 'none',
            'packagist_url' => null,
            'npm_url' => null,
            'social_image' => self::nullableString($meta['og:image'] ?? null),
            'stack' => [],
            'versions' => [],
        ];

        $warnings = [];

        if ($description === null) {
            $warnings[] = 'no_description';
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
     * @throws GithubRateLimitException when recovering the GitHub repo hits a rate limit
     */
    public static function fromNpm(string $url): array
    {
        $package = self::npmNameFromUrl($url);

        if ($package === null) {
            return ['error' => 'invalid_url'];
        }

        return Cache::remember(
            "project_importer:npm:{$package}",
            now()->addHour(),
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
                $branch = GithubClient::fetchDefaultBranchForSlug($repoInfo['slug']) ?? 'main';
                $githubUrl .= '/tree/'.$branch.'/'.$repoInfo['directory'];
                $missingDirectoryReadme = ! GithubClient::subdirectoryHasReadme(
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
            // Fall back to the package name when the registry ships no
            // description so the title never imports blank.
            'title.en' => $description ?? self::prettifyNpmName($name),
            'title.pt' => $description ?? self::prettifyNpmName($name),
            'title.es' => $description ?? self::prettifyNpmName($name),
            'category' => 'javascript_package',
            'package_type' => 'npm',
            'packagist_url' => null,
            'npm_url' => 'https://www.npmjs.com/package/'.$name,
            'stack' => [],
            'topics' => ProjectTopics::normalize(is_array($data['keywords'] ?? null) ? $data['keywords'] : []),
            'versions' => [],
        ];

        $warnings = [];

        if ($description === null) {
            $warnings[] = 'no_description';
        }

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
        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    // YouTube replies with a placeholder shell to bare bots —
                    // a real browser UA gets the og: tag-rich HTML we need.
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                ->get($url);
        } catch (Throwable $e) {
            self::logFetchFailure('youtube', $url, $e);

            return ['error' => 'fetch_failed'];
        }

        if (! $response->successful()) {
            return ['error' => 'fetch_failed'];
        }

        $meta = self::parseMeta($response->body());

        $rawTitle = $meta['og:title'] ?? $meta['title'] ?? null;
        $name = self::cleanYoutubeTitle(is_string($rawTitle) ? $rawTitle : null, $pathSegment);
        $description = self::nullableString($meta['og:description'] ?? $meta['description'] ?? null);

        // Use the channel handle / id for the slug — gives stable URLs that
        // don't shift when the user renames the channel.
        $handle = str_starts_with($pathSegment, '@')
            ? substr($pathSegment, 1)
            : (str_contains($pathSegment, '/') ? explode('/', $pathSegment, 2)[1] : $pathSegment);
        $slug = 'youtube-'.Str::slug($handle);

        $fields = [
            'github_url' => null,
            'slug' => $slug,
            'name' => $name,
            'repo' => null,
            'license' => null,
            'readme_branch' => null,
            'docs_url' => $url,
            'title.en' => $description ?? $name,
            'title.pt' => $description ?? $name,
            'title.es' => $description ?? $name,
            'category' => 'youtube_channel',
            'package_type' => 'none',
            'packagist_url' => null,
            'npm_url' => null,
            'stack' => [],
            'versions' => [],
        ];

        $warnings = [];

        if ($description === null) {
            $warnings[] = 'no_description';
        }

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
        if (! preg_match('~npmjs\.com/package/(@[^/?#]+/[^/?#]+|[^/?#]+)~i', trim($url), $m)) {
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
        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'jeffersongoncalves-site',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->get($url);
        } catch (Throwable $e) {
            self::logFetchFailure('website', $url, $e);

            return ['error' => 'fetch_failed'];
        }

        if (! $response->successful()) {
            return ['error' => 'fetch_failed'];
        }

        $meta = self::parseMeta($response->body());

        $name = self::nameFromHost($host);
        $description = $meta['og:description'] ?? $meta['description'] ?? null;
        $title = $meta['og:title'] ?? $meta['title'] ?? $name;

        $fields = [
            'github_url' => null,
            'slug' => self::siteSlugFromUrl($url),
            'name' => $name,
            'repo' => null,
            'license' => null,
            'readme_branch' => null,
            'docs_url' => $url,
            'title.en' => $description ?? $title,
            'title.pt' => $description ?? $title,
            'title.es' => $description ?? $title,
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

        $warnings = [];

        if ($description === null) {
            $warnings[] = 'no_description';
        }

        return ['fields' => $fields, 'warnings' => $warnings];
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
            if (in_array($key, ['title', 'description', 'og:title', 'og:description', 'og:image'], true)) {
                $meta[$key] = $content;
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $meta;
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
        $repo = GithubClient::fetchRepo($repoSlug);

        if ($repo === null) {
            return ['error' => 'repo_not_found'];
        }

        $branch = is_string($repo['default_branch'] ?? null) ? $repo['default_branch'] : 'main';
        $composer = GithubClient::fetchManifest($repoSlug, $branch, 'composer.json');
        $package = GithubClient::fetchManifest($repoSlug, $branch, 'package.json');
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
            if (GithubClient::fileExists($repoSlug, $branch, $path)) {
                $hasDockerCompose = true;
                break;
            }
        }
        // Standalone Dockerfile counts as a Docker signal only when the
        // repo doesn't ship composer.json / package.json — otherwise a
        // Laravel app with a dev Dockerfile would be misclassified.
        $hasStandaloneDockerfile = $composer === null
            && $package === null
            && GithubClient::fileExists($repoSlug, $branch, 'Dockerfile');
        $branches = GithubClient::fetchBranches($repoSlug);

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

        $fields = [
            'github_url' => $url,
            'slug' => Str::slug($owner.'-'.$repoName),
            'name' => self::prettifyName($repoName),
            'repo' => $repoName,
            'license' => self::normalizeLicense($repo['license'] ?? null),
            'readme_branch' => $branch,
            'docs_url' => self::nullableString($repo['homepage'] ?? null),
            // Mirror the same description across all locales — the importer can't
            // translate, the editor manually edits per-locale later. Same value
            // beats null fields the editor has to clear. Fall back to the repo
            // name when there's no description so the title never imports blank.
            'title.en' => $description ?? self::prettifyName($repoName),
            'title.pt' => $description ?? self::prettifyName($repoName),
            'title.es' => $description ?? self::prettifyName($repoName),
            'category' => $category,
            'package_type' => $packageType,
            'language' => ProjectLanguage::tryFrom((string) ($repo['language'] ?? ''))?->value,
            'packagist_url' => $packagistOwned ? 'https://packagist.org/packages/'.$packagistName : null,
            'npm_url' => $npmPublished ? 'https://www.npmjs.com/package/'.$npmName : null,
            'stack' => ProjectClassifier::stack($composer, $package),
            'topics' => ProjectTopics::normalize(
                is_array($repo['topics'] ?? null) ? $repo['topics'] : [],
                is_array($composer['keywords'] ?? null) ? $composer['keywords'] : [],
                is_array($package['keywords'] ?? null) ? $package['keywords'] : [],
            ),
            'versions' => ProjectClassifier::versions($composer, $branches, $category),
        ];

        $warnings = [];

        if ($category === 'application') {
            $warnings[] = 'category_fallback';
        }

        if ($description === null) {
            $warnings[] = 'no_description';
        }

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
        if (! preg_match('~npmjs\.com/package/(@[^/?#]+/[^/?#]+|[^/?#]+)~i', $npmUrl, $m)) {
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

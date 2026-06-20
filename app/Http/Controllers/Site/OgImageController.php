<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Caches each project's social card behind our own URL so crawlers don't hit
 * GitHub's opengraph endpoint (opengraph.githubassets.com) directly — that
 * endpoint rate-limits aggressively (429). We fetch it once per day, serve it
 * from cache, and fall back to the generic banner when the source is down.
 */
class OgImageController
{
    public function __invoke(string $slug): Response|RedirectResponse
    {
        $project = Project::query()->published()->where('slug', $slug)->first();

        $source = $project !== null ? $this->sourceUrl($project) : null;

        if ($source === null) {
            return $this->fallback();
        }

        $key = 'og-image:'.$slug;

        /** @var array{body: string, type: string}|null $cached */
        $cached = Cache::get($key);

        if ($cached === null) {
            $cached = $this->fetch($source['url'], $source['resolve']);

            if ($cached !== null) {
                Cache::put($key, $cached, now()->addDay());
            }
        }

        if ($cached === null) {
            return $this->fallback();
        }

        return response($cached['body'], 200)
            ->header('Content-Type', $cached['type'])
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /**
     * The image source plus, for untrusted hosts, the curl resolve map that
     * pins the connection to the IP we validated — see publicResolveEntries().
     *
     * @return array{url: string, resolve: list<string>|null}|null
     */
    private function sourceUrl(Project $project): ?array
    {
        if ($project->github_url && preg_match('~github\.com/([^/?#]+/[^/?#]+)~i', $project->github_url, $m)) {
            // Trusted first-party host — no pinning needed.
            return ['url' => 'https://opengraph.githubassets.com/1/'.rtrim($m[1], '/'), 'resolve' => null];
        }

        // social_image is scraped from arbitrary external sites at import time,
        // so it is untrusted. Only proxy it when it resolves to a public host —
        // otherwise /og/{slug}.png becomes an SSRF window into internal services.
        if (! empty($project->social_image)) {
            $resolve = $this->publicResolveEntries((string) $project->social_image);

            if ($resolve !== null) {
                return ['url' => (string) $project->social_image, 'resolve' => $resolve];
            }
        }

        return null;
    }

    /**
     * For a plain http(s) URL whose host resolves only to public IPs, return a
     * curl CURLOPT_RESOLVE entry (`host:port:ip`) pinning the host to the
     * validated IP. Returns null for non-http(s) URLs, unresolvable hosts, or
     * any host pointing at a private/reserved/loopback/link-local range
     * (deny-by-default).
     *
     * Pinning closes the DNS-rebinding (TOCTOU) window: without it the host is
     * resolved once here and again at fetch time, letting an attacker-controlled
     * domain flip to an internal IP between the two lookups.
     *
     * @return list<string>|null
     */
    private function publicResolveEntries(string $url): ?array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $ips = gethostbynamel($host) ?: [];

            foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if ($ips === []) {
            return null;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }
        }

        // Pin to the first validated IP. curl needs the bare host (no brackets).
        $bareHost = trim($host, '[]');

        return ["{$bareHost}:{$port}:{$ips[0]}"];
    }

    /**
     * @param  list<string>|null  $resolve  curl CURLOPT_RESOLVE entries (host:port:ip)
     * @return array{body: string, type: string}|null
     */
    private function fetch(string $source, ?array $resolve = null): ?array
    {
        try {
            $options = [
                // Follow redirects but re-validate every hop: the CURLOPT_RESOLVE
                // pin only covers the first host, so without this a public host
                // could 302 to 169.254.169.254/localhost and defeat the IP guard.
                'allow_redirects' => [
                    'max' => 3,
                    'strict' => true,
                    'referer' => false,
                    'protocols' => ['http', 'https'],
                    'on_redirect' => function ($request, $response, $uri): void {
                        if ($this->publicResolveEntries((string) $uri) === null) {
                            throw new \RuntimeException('OG fetch redirect to non-public host blocked: '.$uri);
                        }
                    },
                ],
            ];

            if ($resolve !== null) {
                $options['curl'] = [CURLOPT_RESOLVE => $resolve];
            }

            $response = Http::timeout(8)->withOptions($options)->get($source);
        } catch (Throwable $e) {
            Log::warning('OgImageController fetch threw', ['source' => $source, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('OgImageController fetch failed', ['source' => $source, 'status' => $response->status()]);

            return null;
        }

        // This endpoint serves images only. Untrusted upstreams (social_image)
        // could return text/html+script; serving that verbatim from our own
        // origin would be stored XSS, so reject anything that isn't an image.
        $type = strtolower(trim((string) $response->header('Content-Type')));

        if (! str_starts_with($type, 'image/')) {
            Log::warning('OgImageController rejected non-image upstream', ['source' => $source, 'type' => $type]);

            return null;
        }

        return [
            'body' => $response->body(),
            'type' => $type,
        ];
    }

    private function fallback(): RedirectResponse
    {
        return redirect(Vite::asset('resources/images/github-og-en.png'), 302);
    }
}

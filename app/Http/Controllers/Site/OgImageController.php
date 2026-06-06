<?php

namespace App\Http\Controllers\Site;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
            $cached = $this->fetch($source);

            if ($cached !== null) {
                Cache::put($key, $cached, now()->addDay());
            }
        }

        if ($cached === null) {
            return $this->fallback();
        }

        return response($cached['body'], 200)
            ->header('Content-Type', $cached['type'])
            ->header('Cache-Control', 'public, max-age=86400');
    }

    private function sourceUrl(Project $project): ?string
    {
        if ($project->github_url && preg_match('~github\.com/([^/?#]+/[^/?#]+)~i', $project->github_url, $m)) {
            return 'https://opengraph.githubassets.com/1/'.rtrim($m[1], '/');
        }

        // social_image is scraped from arbitrary external sites at import time,
        // so it is untrusted. Only proxy it when it resolves to a public host —
        // otherwise /og/{slug}.png becomes an SSRF window into internal services.
        if (! empty($project->social_image) && $this->isPublicHttpUrl((string) $project->social_image)) {
            return (string) $project->social_image;
        }

        return null;
    }

    /**
     * Whether the URL is a plain http(s) URL whose host resolves only to public
     * IPs. Hostnames that fail to resolve, or that point at private/reserved/
     * loopback/link-local ranges, are rejected (deny-by-default).
     */
    private function isPublicHttpUrl(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = $parts['host'];

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
            return false;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{body: string, type: string}|null
     */
    private function fetch(string $source): ?array
    {
        try {
            $response = Http::timeout(8)->get($source);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return [
            'body' => $response->body(),
            'type' => (string) ($response->header('Content-Type') ?: 'image/png'),
        ];
    }

    private function fallback(): RedirectResponse
    {
        return redirect(Vite::asset('resources/images/github-og-en.png'), 302);
    }
}

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

        if (! empty($project->social_image)) {
            return (string) $project->social_image;
        }

        return null;
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

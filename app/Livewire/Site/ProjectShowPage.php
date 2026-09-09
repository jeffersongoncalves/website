<?php

declare(strict_types=1);

namespace App\Livewire\Site;

use App\Enums\ProjectCategory;
use App\Models\Project;
use App\Models\ProjectSlugAlias;
use App\Support\GithubReadme;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use JeffersonGoncalves\HtmlSanitizer\HtmlSanitizer;
use JeffersonGoncalves\NpmReadme\NpmReadme;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Full-page Livewire component shared by /projects/{slug}, /articles/{slug} and
 * /links/{slug}. mount() keeps the old controller's lookup + 301 redirects
 * (retired-slug aliases and wrong-section canonicalisation) — issued straight
 * from the initial request so they stay true 301s. The Filament-plugin version
 * switcher (?v=) is a plain full-reload link (no wire:click) so the route stays
 * eligible for CachePublicPage — a cached page's csrf-token meta tag is stale
 * for every visitor but the other, so an AJAX wire:click POST would 419.
 */
class ProjectShowPage extends Component
{
    #[Url]
    public string $v = '';

    public int $projectId;

    public function mount(string $slug): void
    {
        $project = Project::query()->published()->where('slug', $slug)->first();

        // No live project on this slug — it may be a retired slug. 301 to the
        // current slug instead of 404-ing every old bookmark/backlink.
        if ($project === null) {
            $this->redirectFromAlias($slug);

            return; // unreachable: redirectFromAlias() always aborts.
        }

        // Each project has one canonical section; a request under the wrong one
        // 301s to the right section so the nav highlights and old links resolve.
        if ($project->canonicalRouteName() !== request()->route()?->getName()) {
            $this->abortToCanonical($project);
        }

        $this->projectId = $project->id;
    }

    /**
     * Resolve a retired slug to its project and 301 to the current slug. Aborts
     * 404 when the slug is unknown or its project is no longer published.
     */
    private function redirectFromAlias(string $slug): void
    {
        $alias = ProjectSlugAlias::query()->where('slug', $slug)->first();

        $target = $alias === null
            ? null
            : Project::query()->published()->whereKey($alias->project_id)->first();

        abort_if($target === null, 404);

        $this->abortToCanonical($target);
    }

    /**
     * 301 to the project's canonical section URL, preserving the query string
     * (e.g. the ?v= readme-version param).
     */
    private function abortToCanonical(Project $project): void
    {
        $url = route($project->canonicalRouteName(), ['slug' => $project->slug]);

        if (($query = request()->getQueryString()) !== null && $query !== '') {
            $url .= '?'.$query;
        }

        // Build the RedirectResponse directly rather than via redirect(): during
        // a component's lifecycle Livewire swaps the `redirect` binding for its
        // own fluent Redirector, which wouldn't yield a real 301 response here.
        abort(new RedirectResponse($url, 301));
    }

    public function render(): View
    {
        // Keep the published() scope on every re-render (wire:click version
        // switch) so a row unpublished/rescheduled mid-session stops rendering.
        $project = Project::query()->published()->findOrFail($this->projectId);

        $isFilamentPlugin = $project->category === ProjectCategory::FilamentPlugin;
        $versions = $isFilamentPlugin && is_array($project->versions)
            ? GithubReadme::sortedVersions($project->versions)
            : [];

        $activeVersion = null;
        $ref = null;
        $versionGroups = [];

        if ($versions !== []) {
            $overrides = is_array($project->branch_overrides) ? $project->branch_overrides : [];
            $activeVersion = in_array($this->v, $versions, true) ? $this->v : (string) end($versions);
            $ref = $this->resolveBranchForVersion($activeVersion, $versions, $overrides);

            // Group consecutive versions that resolve to the same real branch so
            // a plugin whose v4 and v5 both live on `master` shows one "v4/v5"
            // chip rather than two chips pointing at the identical README. The
            // chip's ?v= uses the highest version of the group (the default).
            foreach ($versions as $version) {
                $branch = $this->resolveBranchForVersion($version, $versions, $overrides);
                $last = count($versionGroups) - 1;

                if ($last >= 0 && $versionGroups[$last]['branch'] === $branch) {
                    $versionGroups[$last]['versions'][] = $version;
                } else {
                    $versionGroups[] = ['versions' => [$version], 'branch' => $branch];
                }
            }

            $versionGroups = array_map(static function (array $group): array {
                $group['label'] = implode('/', $group['versions']);
                $group['param'] = (string) end($group['versions']);

                return $group;
            }, $versionGroups);
        } elseif (! empty($project->readme_branch)) {
            $ref = $project->readme_branch;
        }

        // README comes from GitHub when the project has a repo; npm-only
        // packages (no github_url) fall back to the README shipped inline in
        // the npm registry document.
        $readmeHtml = match (true) {
            (bool) $project->github_url => GithubReadme::fetchHtml($project->github_url, $ref),
            (bool) $project->npm_url => NpmReadme::fetchHtml($project->npm_url),
            default => null,
        };

        if ($readmeHtml !== null) {
            // README HTML comes from an arbitrary (possibly third-party) repo —
            // strip scripts/handlers before our own link/image rewriting adds
            // the safe target/rel/loading attributes on top.
            $readmeHtml = HtmlSanitizer::clean($readmeHtml);

            if ($versions !== []) {
                $readmeHtml = GithubReadme::rewriteSelfRepoLinks(
                    $readmeHtml,
                    $project->github_url,
                    $project->slug,
                    $versions,
                    is_array($project->branch_overrides) ? $project->branch_overrides : []
                );
            }

            $selfHost = (string) parse_url(config('app.url'), PHP_URL_HOST);
            $readmeHtml = GithubReadme::markExternalLinks($readmeHtml, $selfHost);
            $readmeHtml = GithubReadme::rewriteOutboundLinks($readmeHtml);
            $readmeHtml = GithubReadme::lazyloadImages($readmeHtml);
            $readmeHtml = GithubReadme::ensureImageAlt($readmeHtml);
            $readmeHtml = GithubReadme::wrapTables($readmeHtml);
        }

        return view('livewire.site.project-show', compact('project', 'readmeHtml', 'versions', 'versionGroups', 'activeVersion', 'ref'))
            ->layout('components.site.layouts.app', ['seoData' => $project]);
    }

    /**
     * Resolve the real GitHub branch a tracked Filament version renders from:
     * the positional auto-branch (1.x, 2.x, …) unless a branch_overrides entry
     * remaps it. Returns null when the version is not tracked.
     *
     * @param  list<string>  $versions  ordered ascending (e.g. ['v3','v4','v5'])
     * @param  array<string,string>  $overrides  auto-branch => real-branch
     */
    private function resolveBranchForVersion(string $version, array $versions, array $overrides): ?string
    {
        $autoBranch = GithubReadme::branchForFilamentVersion($version, $versions);

        if ($autoBranch === null) {
            return null;
        }

        $override = isset($overrides[$autoBranch]) ? trim((string) $overrides[$autoBranch]) : '';

        return $override !== '' ? $override : $autoBranch;
    }
}

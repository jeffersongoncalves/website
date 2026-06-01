<?php

namespace App\Http\Controllers\Site;

use App\Enums\ProjectCategory;
use App\Models\Project;
use App\Models\ProjectSlugAlias;
use App\Support\GithubReadme;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectViewController
{
    public function __invoke(Request $request, string $slug): View|RedirectResponse
    {
        $project = Project::query()
            ->published()
            ->where('slug', $slug)
            ->first();

        // No live project on this slug — it may be a retired slug. 301 to the
        // project's current slug (preserving the ?v= readme-version param)
        // instead of 404-ing every old bookmark/backlink.
        if ($project === null) {
            return $this->redirectFromAlias($slug, $request);
        }

        // Each project has one canonical section: articles → /articles/{slug},
        // everything else → /projects/{slug}. A request under the wrong section
        // 301s to the right one so the nav highlights correctly and old
        // /projects/{article-slug} links keep resolving.
        if ($this->canonicalRouteName($project) !== $request->route()?->getName()) {
            return $this->redirectToCanonical($project, $request);
        }

        $isFilamentPlugin = $project->category === ProjectCategory::FilamentPlugin;
        $versions = $isFilamentPlugin && is_array($project->versions) ? $project->versions : [];

        $activeVersion = null;
        $ref = null;

        if ($versions !== []) {
            $requested = $request->string('v')->toString();
            $activeVersion = in_array($requested, $versions, true) ? $requested : (string) end($versions);
            $autoBranch = GithubReadme::branchForFilamentVersion($activeVersion, $versions);
            $overrides = is_array($project->branch_overrides) ? $project->branch_overrides : [];
            $override = ($autoBranch !== null && isset($overrides[$autoBranch]))
                ? trim((string) $overrides[$autoBranch])
                : '';
            $ref = $override !== '' ? $override : $autoBranch;
        } elseif (! empty($project->readme_branch)) {
            $ref = $project->readme_branch;
        }

        $readmeHtml = $project->github_url
            ? GithubReadme::fetchHtml($project->github_url, $ref)
            : null;

        if ($readmeHtml !== null) {
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
            $readmeHtml = GithubReadme::lazyloadImages($readmeHtml);
            $readmeHtml = GithubReadme::wrapTables($readmeHtml);
        }

        return view('site.projects.show', compact('project', 'readmeHtml', 'versions', 'activeVersion', 'ref'));
    }

    /**
     * Resolve a retired slug to its project and 301 to the current slug. Aborts
     * 404 when the slug is unknown or its project is no longer published.
     */
    private function redirectFromAlias(string $slug, Request $request): RedirectResponse
    {
        $alias = ProjectSlugAlias::query()->where('slug', $slug)->first();

        $target = $alias === null
            ? null
            : Project::query()->published()->whereKey($alias->project_id)->first();

        abort_if($target === null, 404);

        return $this->redirectToCanonical($target, $request);
    }

    /**
     * 301 to the project's canonical section URL, preserving the query string
     * (e.g. the ?v= readme-version param). Used both for retired-slug aliases
     * and to move a project served under the wrong section.
     */
    private function redirectToCanonical(Project $project, Request $request): RedirectResponse
    {
        $url = route($this->canonicalRouteName($project), ['slug' => $project->slug]);

        if (($query = $request->getQueryString()) !== null && $query !== '') {
            $url .= '?'.$query;
        }

        return redirect($url, 301);
    }

    /**
     * Articles render under /articles/{slug}; every other category under
     * /projects/{slug}.
     */
    private function canonicalRouteName(Project $project): string
    {
        return $project->category === ProjectCategory::Article
            ? 'articles.show'
            : 'projects.show';
    }
}

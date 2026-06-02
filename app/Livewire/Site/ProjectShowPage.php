<?php

namespace App\Livewire\Site;

use App\Enums\ProjectCategory;
use App\Models\Project;
use App\Models\ProjectSlugAlias;
use App\Support\GithubReadme;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Full-page Livewire component shared by /projects/{slug}, /articles/{slug} and
 * /links/{slug}. mount() keeps the old controller's lookup + 301 redirects
 * (retired-slug aliases and wrong-section canonicalisation) — issued straight
 * from the initial request so they stay true 301s. The Filament-plugin version
 * switcher (?v=) is now reactive: clicking a version swaps the README in place
 * via wire:click instead of a full reload. Excluded from CachePublicPage.
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

    public function setVersion(string $version): void
    {
        $this->v = $version;
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
        $project = Project::query()->findOrFail($this->projectId);

        $isFilamentPlugin = $project->category === ProjectCategory::FilamentPlugin;
        $versions = $isFilamentPlugin && is_array($project->versions) ? $project->versions : [];

        $activeVersion = null;
        $ref = null;

        if ($versions !== []) {
            $activeVersion = in_array($this->v, $versions, true) ? $this->v : (string) end($versions);
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

        return view('livewire.site.project-show', compact('project', 'readmeHtml', 'versions', 'activeVersion', 'ref'))
            ->layout('components.site.layouts.app', ['seoData' => $project]);
    }
}

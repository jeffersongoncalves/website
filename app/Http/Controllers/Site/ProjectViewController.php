<?php

namespace App\Http\Controllers\Site;

use App\Enums\ProjectCategory;
use App\Models\Project;
use App\Support\GithubReadme;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ProjectViewController
{
    public function __invoke(Request $request, string $slug): View
    {
        /** @var Project $project */
        $project = Project::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

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
        }

        return view('site.projects.show', compact('project', 'readmeHtml', 'versions', 'activeVersion', 'ref'));
    }
}

<?php

namespace App\Http\Controllers\Site;

use App\Enums\ProjectCategory;
use App\Models\Project;
use App\Support\GithubReadme;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ProjectViewController
{
    public function __invoke(Request $request, string $locale, string $slug): View
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

        $readmeHtml = GithubReadme::fetchHtml($project->github_url, $ref);

        return view('site.projects.show', compact('project', 'readmeHtml', 'versions', 'activeVersion', 'ref'));
    }
}

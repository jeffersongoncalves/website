<?php

namespace App\Http\Controllers\Site;

use App\Enums\ProjectCategory;
use App\Models\Project;
use App\Support\GithubReadme;
use App\Support\SiteStats;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ProjectController
{
    public function index(Request $request): View
    {
        $cat  = $request->string('cat')->toString();
        $sort = $request->string('sort', 'stars')->toString();

        $query = Project::query()->published();

        $category = ProjectCategory::tryFrom($cat);
        if ($category) {
            $query->byCategory($category);
        }

        match ($sort) {
            'downloads' => $query->orderByDesc('downloads'),
            'name'      => $query->orderBy('name'),
            default     => $query->orderByDesc('stars'),
        };

        $projects = $query->get();

        $stats  = SiteStats::all();
        $counts = [
            'total'    => $stats['repos'],
            'filament' => $stats['filament'],
            'laravel'  => $stats['laravel'],
            'starter'  => $stats['starter'],
            'tool'     => $stats['tool'],
        ];

        return view('site.projects.index', [
            'projects'   => $projects,
            'activeCat'  => $category ? $category->value : 'all',
            'activeSort' => $sort,
            'categories' => ProjectCategory::cases(),
            'counts'     => $counts,
        ]);
    }

    public function show(Request $request, string $locale, string $slug): View
    {
        $project = Project::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $isFilamentPlugin = $project->category === ProjectCategory::FilamentPlugin;
        $versions         = $isFilamentPlugin ? ($project->versions ?? []) : [];

        $activeVersion = null;
        $ref           = null;

        if ($isFilamentPlugin && ! empty($versions)) {
            $requested     = $request->string('v')->toString();
            $activeVersion = in_array($requested, $versions, true) ? $requested : end($versions);
            $ref           = GithubReadme::branchForFilamentVersion($activeVersion, $versions);
        }

        $readmeHtml = GithubReadme::fetchHtml($project->github_url, $ref);

        return view('site.projects.show', compact('project', 'readmeHtml', 'versions', 'activeVersion', 'ref'));
    }
}

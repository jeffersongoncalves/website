<?php

namespace App\Http\Controllers\Site;

use App\Enums\ProjectCategory;
use App\Models\Project;
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

        return view('site.projects.index', [
            'projects'   => $projects,
            'activeCat'  => $category ? $category->value : 'all',
            'activeSort' => $sort,
            'categories' => ProjectCategory::cases(),
        ]);
    }

    public function show(string $locale, string $slug): View
    {
        $project = Project::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return view('site.projects.show', compact('project'));
    }
}

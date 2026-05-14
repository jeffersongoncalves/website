<?php

namespace App\Http\Controllers\Site;

use App\Enums\ProjectCategory;
use App\Models\Project;
use App\Support\SiteStats;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ProjectController
{
    public function __invoke(Request $request): View
    {
        $cat = $request->string('cat')->toString();
        $sort = $request->string('sort', 'stars')->toString();

        $query = Project::query()->published();

        $category = ProjectCategory::tryFrom($cat);
        if ($category) {
            $query->byCategory($category);
        }

        match ($sort) {
            'downloads' => $query->orderByDesc('downloads'),
            'name' => $query->orderBy('name'),
            default => $query->orderByDesc('stars'),
        };

        $projects = $query->get();

        $stats = SiteStats::all();
        $counts = [
            'total' => $stats['repos'],
            'filament' => $stats['filament'],
            'laravel' => $stats['laravel'],
            'starter' => $stats['starter'],
            'tool' => $stats['tool'],
        ];

        return view('site.projects.index', [
            'projects' => $projects,
            'activeCat' => $category ? $category->value : 'all',
            'activeSort' => $sort,
            'categories' => ProjectCategory::cases(),
            'counts' => $counts,
        ]);
    }
}

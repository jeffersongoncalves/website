<?php

namespace App\Http\Controllers\Site;

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Support\SiteStats;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController
{
    public function __invoke(Request $request): View
    {
        $cat = $request->string('cat')->toString();
        $sort = $request->string('sort', 'stars')->toString();
        $role = $request->string('role')->toString();
        $search = trim($request->string('search')->toString());
        $language = $request->string('language')->toString();

        $query = Project::query()->published();

        $category = ProjectCategory::tryFrom($cat);
        if ($category) {
            $query->byCategory($category);
        }

        // Distinct languages present in the published catalogue, busiest first
        // — drives the language facet dropdown. Read raw (DB::table, no enum
        // cast) so the list is plain strings for the <select> + comparison.
        $languages = DB::table('projects')
            ->where('status', ProjectStatus::Published->value)
            ->whereNotNull('language')
            ->where('language', '!=', '')
            ->selectRaw('language, count(*) as total')
            ->groupBy('language')
            ->orderByDesc('total')
            ->orderBy('language')
            ->pluck('language')
            ->all();

        $activeLanguage = in_array($language, $languages, true) ? $language : '';
        if ($activeLanguage !== '') {
            $query->byLanguage($activeLanguage);
        }

        // Topic filter is driven by the #topic chips on the cards — a single
        // slug, matched against the JSON topics array.
        $topic = $request->string('topic')->toString();
        $activeTopic = preg_match('/^[a-z0-9-]{1,50}$/', $topic) ? $topic : '';
        if ($activeTopic !== '') {
            $query->whereJsonContains('topics', $activeTopic);
        }

        $activeRole = in_array($role, ['authored', 'maintainer', 'daily_driver'], true) ? $role : 'all';
        if ($activeRole === 'maintainer') {
            $query->maintained();
        } elseif ($activeRole === 'authored') {
            $query->authored();
        } elseif ($activeRole === 'daily_driver') {
            $query->where('is_daily_driver', true);
        }

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('repo', 'like', $like);
            });
        }

        match ($sort) {
            'downloads' => $query->orderByDesc('downloads'),
            'name' => $query->orderBy('name'),
            default => $query->orderByDesc('stars'),
        };

        $projects = $query->paginate(10)->withQueryString();

        $stats = SiteStats::all();
        $counts = [
            'total' => $stats['repos'],
            'catalogue' => $stats['catalogue'],
            'filament' => $stats['filament'],
            'laravel' => $stats['laravel'],
            'starter' => $stats['starter'],
            'tool' => $stats['tool'],
            'maintained' => $stats['maintained'],
            'daily_drivers' => $stats['daily_drivers'],
        ];

        return view('site.projects.index', [
            'projects' => $projects,
            'activeCat' => $category ? $category->value : 'all',
            'activeSort' => $sort,
            'activeRole' => $activeRole,
            'activeSearch' => $search,
            'activeLanguage' => $activeLanguage,
            'activeTopic' => $activeTopic,
            'categories' => ProjectCategory::cases(),
            'languages' => $languages,
            'popularTopics' => array_slice($stats['topics'], 0, 15),
            'counts' => $counts,
        ]);
    }
}

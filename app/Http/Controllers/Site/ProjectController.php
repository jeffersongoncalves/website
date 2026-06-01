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
        $role = $request->string('role')->toString();
        $search = trim($request->string('search')->toString());
        $language = $request->string('language')->toString();

        $query = Project::query()->published();

        $category = ProjectCategory::tryFrom($cat);
        if ($category) {
            $query->byCategory($category);
        }

        // The language facet (and the count cards) reuse the persisted SiteStats
        // breakdown — already computed busiest-first on sync — instead of
        // re-running a GROUP BY on every request.
        $stats = SiteStats::all();
        $languages = array_column($stats['languages'], 'language');

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

        // Origin facet — separate Jefferson's own/curated catalogue from the
        // third-party repos imported off the GitHub stars feed.
        $source = $request->string('source')->toString();
        $activeSource = in_array($source, ['own', 'starred'], true) ? $source : 'all';
        if ($activeSource === 'own') {
            $query->own();
        } elseif ($activeSource === 'starred') {
            $query->starred();
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
            'activeSource' => $activeSource,
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

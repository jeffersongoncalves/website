<?php

namespace App\Http\Controllers\Site;

use App\Enums\ProjectCategory;
use App\Models\Project;
use App\Support\SiteStats;
use Illuminate\Contracts\View\View;
use Illuminate\Database\PostgresConnection;
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

        // The /projects catalogue is code only — packages, plugins, starter
        // kits, tools. Articles live on /articles and external reference links
        // (sites, YouTube channels, learning resources, awesome lists) on the
        // /links hub; both carry stars=0 and would only be noise here.
        $query = Project::query()
            ->published()
            ->whereIn('category', array_map(
                fn (ProjectCategory $c): string => $c->value,
                ProjectCategory::catalogueCases(),
            ));

        $category = ProjectCategory::tryFrom($cat);
        if ($category && ! $category->isExternalLink() && $category !== ProjectCategory::Article) {
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
            // Postgres LIKE is case-sensitive — use ILIKE there so a search for
            // "filament" matches "Filament". MySQL/SQLite LIKE already folds case.
            $operator = $query->getConnection() instanceof PostgresConnection ? 'ilike' : 'like';
            $query->where(function ($q) use ($like, $operator) {
                $q->where('name', $operator, $like)
                    ->orWhere('repo', $operator, $like);
            });
        }

        match ($sort) {
            'downloads' => $query->orderByDesc('downloads'),
            'name' => $query->orderBy('name'),
            default => $query->orderByDesc('stars'),
        };

        $projects = $query->paginate(10)->withQueryString()
            // Anchor pagination to the catalogue so paging doesn't jump to top.
            ->fragment('catalogue');

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
            'activeCat' => ($category && ! $category->isExternalLink() && $category !== ProjectCategory::Article) ? $category->value : 'all',
            'activeSort' => $sort,
            'activeRole' => $activeRole,
            'activeSource' => $activeSource,
            'activeSearch' => $search,
            'activeLanguage' => $activeLanguage,
            'activeTopic' => $activeTopic,
            'categories' => ProjectCategory::catalogueCases(),
            'languages' => $languages,
            'popularTopics' => array_slice($stats['topics'], 0, 15),
            'counts' => $counts,
        ]);
    }
}

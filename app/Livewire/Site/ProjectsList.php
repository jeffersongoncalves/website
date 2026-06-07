<?php

namespace App\Livewire\Site;

use App\Enums\ProjectCategory;
use App\Models\Project;
use App\Support\SiteStats;
use Illuminate\Contracts\View\View;
use Illuminate\Database\PostgresConnection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The interactive /projects catalogue: code only (packages, plugins, starter
 * kits, tools) — articles live on /articles and external links on /links. Every
 * facet is a #[Url] property so the filtered state stays shareable/bookmarkable
 * and survives back/forward, exactly like the old query-string controller did.
 */
class ProjectsList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $cat = '';

    #[Url]
    public string $language = '';

    #[Url]
    public string $sort = 'stars';

    #[Url]
    public string $role = 'all';

    #[Url]
    public string $source = 'all';

    #[Url]
    public string $topic = '';

    public function paginationView(): string
    {
        return 'pagination.site-livewire';
    }

    /** Any facet change (search/cat/language/sort bound via wire:model) resets to page 1. */
    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
        $this->resetPage();
    }

    public function setSource(string $source): void
    {
        $this->source = $source;
        $this->resetPage();
    }

    public function setTopic(string $topic): void
    {
        $this->topic = $topic;
        $this->resetPage();
    }

    public function clearTopic(): void
    {
        $this->topic = '';
        $this->resetPage();
    }

    public function render(): View
    {
        $search = trim($this->search);

        $query = Project::query()
            ->published()
            ->whereIn('category', array_map(
                fn (ProjectCategory $c): string => $c->value,
                ProjectCategory::catalogueCases(),
            ));

        $category = ProjectCategory::tryFrom($this->cat);
        if ($category && ! $category->isExternalLink() && $category !== ProjectCategory::Article) {
            $query->byCategory($category);
        }

        // The language facet (and the count cards) reuse the persisted SiteStats
        // breakdown — already computed busiest-first on sync — instead of
        // re-running a GROUP BY on every request.
        $stats = SiteStats::all();
        $languages = array_column($stats['languages'], 'language');

        $activeLanguage = in_array($this->language, $languages, true) ? $this->language : '';
        if ($activeLanguage !== '') {
            $query->byLanguage($activeLanguage);
        }

        // Topic filter is driven by the #topic chips on the cards — a single
        // slug, matched against the JSON topics array.
        $activeTopic = preg_match('/^[a-z0-9-]{1,50}$/', $this->topic) ? $this->topic : '';
        if ($activeTopic !== '') {
            $query->whereJsonContains('topics', $activeTopic);
        }

        $activeRole = in_array($this->role, ['authored', 'maintainer', 'daily_driver'], true) ? $this->role : 'all';
        if ($activeRole === 'maintainer') {
            $query->maintained();
        } elseif ($activeRole === 'authored') {
            $query->authored();
        } elseif ($activeRole === 'daily_driver') {
            $query->where('is_daily_driver', true);
        }

        // Origin facet — separate Jefferson's own/curated catalogue from the
        // third-party repos imported off the GitHub stars feed.
        $activeSource = in_array($this->source, ['own', 'starred'], true) ? $this->source : 'all';
        if ($activeSource === 'own') {
            $query->own();
        } elseif ($activeSource === 'starred') {
            $query->starred();
        }

        if ($search !== '') {
            // Escape the backslash first (it's the LIKE escape char) so a user
            // backslash can't turn the following %/_ into a literal/escape.
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search).'%';
            // Postgres LIKE is case-sensitive — use ILIKE there so a search for
            // "filament" matches "Filament". MySQL/SQLite LIKE already folds case.
            $operator = $query->getConnection() instanceof PostgresConnection ? 'ilike' : 'like';
            $query->where(function ($q) use ($like, $operator): void {
                $q->where('name', $operator, $like)
                    ->orWhere('repo', $operator, $like);
            });
        }

        match ($this->sort) {
            'downloads' => $query->orderByDesc('downloads'),
            'name' => $query->orderBy('name'),
            default => $query->orderByDesc('stars'),
        };

        $projects = $query->paginate(10);

        return view('livewire.site.projects-list', [
            'projects' => $projects,
            'activeCat' => ($category && ! $category->isExternalLink() && $category !== ProjectCategory::Article) ? $category->value : 'all',
            'activeRole' => $activeRole,
            'activeSource' => $activeSource,
            'activeLanguage' => $activeLanguage,
            'activeTopic' => $activeTopic,
            'categories' => ProjectCategory::catalogueCases(),
            'languages' => $languages,
            'popularTopics' => array_slice($stats['topics'], 0, 15),
        ]);
    }
}

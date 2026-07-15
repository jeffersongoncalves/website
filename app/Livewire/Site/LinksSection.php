<?php

declare(strict_types=1);

namespace App\Livewire\Site;

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Services\PostHogService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * One external-link section on /links (sites, YouTube channels, learning
 * resources, awesome lists). Its search/sort/topic/page state is mapped to
 * anchor-scoped query-string keys (q_<anchor>, sort_<anchor>, topic_<anchor>,
 * <anchor>) via queryString() so multiple sections coexist on one page without
 * clobbering each other — the same scheme the old controller used.
 */
class LinksSection extends Component
{
    use WithPagination;

    private const PER_PAGE = 6;

    public ProjectCategory $category;

    public int $index = 0;

    public string $anchor = '';

    public string $search = '';

    public string $dir = 'asc';

    public string $topic = '';

    public function mount(ProjectCategory $category, int $index = 0): void
    {
        $this->category = $category;
        $this->index = $index;
        $this->anchor = $category->linksSection() ?? $category->value;
    }

    /**
     * Anchor-scoped query-string keys so each section stays independent. This
     * runs (via the SupportQueryString hook) before mount() sets $this->anchor,
     * so resolve the anchor straight from the bound `category` param instead.
     *
     * @return array<string, array{as: string, except: string}>
     */
    protected function queryString(): array
    {
        $anchor = $this->resolveAnchor();

        return [
            'search' => ['as' => 'q_'.$anchor, 'except' => ''],
            'dir' => ['as' => 'sort_'.$anchor, 'except' => 'asc'],
            'topic' => ['as' => 'topic_'.$anchor, 'except' => ''],
        ];
    }

    private function resolveAnchor(): string
    {
        if ($this->anchor !== '') {
            return $this->anchor;
        }

        if (isset($this->category)) {
            return $this->category->linksSection() ?? $this->category->value;
        }

        return '';
    }

    public function paginationView(): string
    {
        return 'pagination.site-livewire';
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'dir', 'topic'], true)) {
            $this->resetPage($this->anchor);
            $this->trackFilterChanged($property);
        }
    }

    public function setTopic(string $topic): void
    {
        $this->topic = $topic;
        $this->resetPage($this->anchor);
        $this->trackFilterChanged('topic');
    }

    public function clearTopic(): void
    {
        $this->topic = '';
        $this->resetPage($this->anchor);
        $this->trackFilterChanged('topic');
    }

    private function trackFilterChanged(string $facet): void
    {
        app(PostHogService::class)->capture(session()->getId(), 'external_links_filtered', [
            'facet' => $facet,
            'section' => $this->anchor,
            'search' => $this->search,
            'dir' => $this->dir,
            'topic' => $this->topic,
        ]);
    }

    public function render(): View
    {
        $query = Project::query()->published()->byCategory($this->category);

        $search = trim($this->search);
        if ($search !== '') {
            // Escape the backslash first (LIKE escape char) before %/_.
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search).'%';
            // Postgres LIKE is case-sensitive — use ILIKE there.
            $operator = $query->getConnection() instanceof PostgresConnection ? 'ilike' : 'like';
            $query->where(function ($q) use ($like, $operator): void {
                $q->where('name', $operator, $like)
                    ->orWhere('repo', $operator, $like);
            });
        }

        $activeTopic = preg_match('/^[a-z0-9-]{1,50}$/', $this->topic) === 1 ? $this->topic : '';
        if ($activeTopic !== '') {
            $query->whereJsonContains('topics', $activeTopic);
        }

        $dir = $this->dir === 'desc' ? 'desc' : 'asc';

        $projects = $query
            ->orderBy('name', $dir)
            ->paginate(self::PER_PAGE, ['*'], $this->anchor);

        return view('livewire.site.links-section', [
            'projects' => $projects,
            'activeTopic' => $activeTopic,
            'topics' => $this->sectionTopics(),
        ]);
    }

    /**
     * The most-used topics among this section's published projects (top 12).
     * Aggregated in PHP so it stays portable across DB engines.
     *
     * Cached per category — the source rows only change on a project save, and
     * `ProjectObserver` flushes `links_topics:*`. Without the cache this full
     * chunked scan ran on every render of every section on the (non-page-cached)
     * /links hub.
     *
     * @return list<string>
     */
    private function sectionTopics(): array
    {
        return Cache::rememberForever(self::topicsCacheKey($this->category), function (): array {
            $counts = [];

            DB::table('projects')
                ->where('status', ProjectStatus::Published->value)
                ->where('category', $this->category->value)
                ->whereNotNull('topics')
                ->select('topics')
                ->orderBy('id')
                ->chunk(500, function ($rows) use (&$counts): void {
                    foreach ($rows as $row) {
                        $topics = json_decode((string) $row->topics, true);

                        if (! is_array($topics)) {
                            continue;
                        }

                        foreach ($topics as $topic) {
                            if (is_string($topic) && $topic !== '') {
                                $counts[$topic] = ($counts[$topic] ?? 0) + 1;
                            }
                        }
                    }
                });

            arsort($counts);

            return array_slice(array_keys($counts), 0, 12);
        });
    }

    public static function topicsCacheKey(ProjectCategory $category): string
    {
        return 'links_topics:'.$category->value;
    }
}

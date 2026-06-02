<?php

namespace App\Http\Controllers\Site;

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Database\PostgresConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The /links hub — external reference links (sites, YouTube channels, learning
 * resources, awesome lists) pulled out of the code catalogue at /projects.
 * Each type renders as its own section with its own independent search, sort
 * (name asc/desc), topic chips and paginator — all keyed by the section anchor
 * (?q_sites=, ?sort_sites=, ?topic_sites=, ?sites=2) so filtering one section
 * never disturbs the others.
 */
class LinksController
{
    private const PER_PAGE = 6;

    public function __invoke(Request $request): View
    {
        $sections = [];

        foreach (ProjectCategory::externalLinkCases() as $cat) {
            $base = Project::query()->published()->byCategory($cat);

            // Keep the section (with its filter bar) as long as the type has any
            // published rows — even when the current filter empties it.
            if (! (clone $base)->exists()) {
                continue;
            }

            $anchor = $cat->linksSection() ?? $cat->value;

            $search = trim($request->string("q_{$anchor}")->toString());
            $dir = $request->string("sort_{$anchor}")->toString() === 'desc' ? 'desc' : 'asc';
            $topic = $request->string("topic_{$anchor}")->toString();
            $activeTopic = preg_match('/^[a-z0-9-]{1,50}$/', $topic) === 1 ? $topic : '';

            $query = clone $base;

            if ($search !== '') {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                // Postgres LIKE is case-sensitive — use ILIKE there.
                $operator = $query->getConnection() instanceof PostgresConnection ? 'ilike' : 'like';
                $query->where(function ($q) use ($like, $operator): void {
                    $q->where('name', $operator, $like)
                        ->orWhere('repo', $operator, $like);
                });
            }

            if ($activeTopic !== '') {
                $query->whereJsonContains('topics', $activeTopic);
            }

            $projects = $query
                ->orderBy('name', $dir)
                ->paginate(self::PER_PAGE, ['*'], $anchor)
                ->withQueryString()
                // Anchor pagination links to the section so paging lands on it
                // instead of scrolling to the top of /links.
                ->fragment($anchor);

            $sections[] = [
                'category' => $cat,
                'anchor' => $anchor,
                'projects' => $projects,
                'search' => $search,
                'dir' => $dir,
                'activeTopic' => $activeTopic,
                'topics' => $this->sectionTopics($cat),
            ];
        }

        return view('site.links.index', ['sections' => $sections]);
    }

    /**
     * The most-used topics among a section's published projects (top 12).
     * Aggregated in PHP so it stays portable across DB engines.
     *
     * @return list<string>
     */
    private function sectionTopics(ProjectCategory $cat): array
    {
        $counts = [];

        DB::table('projects')
            ->where('status', ProjectStatus::Published->value)
            ->where('category', $cat->value)
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
    }
}

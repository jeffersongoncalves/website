<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\ProjectCategory;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\PostgresConnection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Searches the public catalogue — code projects, articles and curated
 * external links — the same rows and scopes /projects, /articles and /links
 * query. Read-only, published-only; mirrors App\Livewire\Site\ProjectsList's
 * filters instead of re-deriving them.
 */
#[Description('Search Jefferson Gonçalves\' portfolio: open-source projects (Filament plugins, Laravel packages, starter kits, tools), technical articles and curated external links. Returns matching pages with their canonical URL.')]
class SearchProjectsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $section = (string) $request->get('section', 'all');
        $query = trim((string) $request->get('query', ''));
        $category = ProjectCategory::tryFrom((string) $request->get('category', ''));
        $limit = max(1, min(50, $request->integer('limit', 20)));

        $builder = Project::query()->published();

        $builder->where(function ($q) use ($section): void {
            match ($section) {
                'articles' => $q->byCategory(ProjectCategory::Article),
                'links' => $q->whereIn('category', array_map(
                    fn (ProjectCategory $c): string => $c->value,
                    ProjectCategory::externalLinkCases(),
                )),
                'projects' => $q->whereIn('category', array_map(
                    fn (ProjectCategory $c): string => $c->value,
                    ProjectCategory::catalogueCases(),
                )),
                default => null,
            };
        });

        if ($category) {
            $builder->byCategory($category);
        }

        if ($query !== '') {
            $isPostgres = $builder->getConnection() instanceof PostgresConnection;
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $query).'%';
            $operator = $isPostgres ? 'ilike' : 'like';
            // topics/stack are json(b) columns — Postgres needs an explicit
            // ::text cast before ILIKE/LIKE will accept them; sqlite stores
            // them as plain text already, so the cast would just be a no-op
            // there (skipped rather than emitting invalid sqlite syntax).
            $topicsColumn = $isPostgres ? 'topics::text' : 'topics';
            $stackColumn = $isPostgres ? 'stack::text' : 'stack';
            $builder->where(function ($q) use ($like, $operator, $topicsColumn, $stackColumn): void {
                $q->where('name', $operator, $like)
                    ->orWhere('repo', $operator, $like)
                    ->orWhereRaw("{$topicsColumn} {$operator} ?", [$like])
                    ->orWhereRaw("{$stackColumn} {$operator} ?", [$like]);
            });
        }

        $projects = $builder->orderByDesc('stars')->orderBy('name')->limit($limit)->get();

        if ($projects->isEmpty()) {
            return Response::text('No published pages matched that search.');
        }

        $locale = (string) $request->get('locale', 'en');
        $lines = $projects->map(function (Project $project) use ($locale): string {
            $title = $project->localizedTitle($locale);
            $summary = $title !== null ? ': '.$title : '';

            return '- ['.$project->name.']('.$project->publicUrl().') ('.$project->category->getLabel().')'.$summary;
        });

        return Response::text($lines->implode("\n"));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Free-text search over the project/article/link name, repo, tech stack and topics.'),

            'section' => $schema->string()
                ->enum(['all', 'projects', 'articles', 'links'])
                ->description('Restrict to the code catalogue, articles, or curated external links.')
                ->default('all'),

            'category' => $schema->string()
                ->enum(array_map(fn (ProjectCategory $c): string => $c->value, ProjectCategory::cases()))
                ->description('Exact category filter, e.g. filament_plugin, laravel_package, starter_kit.'),

            'limit' => $schema->integer()
                ->description('Max results to return (1-50).')
                ->default(20),

            'locale' => $schema->string()
                ->enum(config('locale-cookie.supported'))
                ->description('Language for each result\'s one-line summary. Falls back to English when the translation is missing.')
                ->default('en'),
        ];
    }
}

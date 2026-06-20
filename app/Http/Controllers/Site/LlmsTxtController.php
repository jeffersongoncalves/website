<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Enums\ProjectCategory;
use App\Models\Project;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * /llms.txt — a plain-text, LLM-readable map of the site (the llmstxt.org
 * convention). Lists the main sections plus the owner's authored open-source
 * projects and articles, each as a `- [name](url): summary` link so an
 * assistant can navigate the site without scraping HTML. DB-driven (two
 * queries), cached forever and invalidated on any project change via
 * ProjectObserver.
 */
class LlmsTxtController
{
    public const CACHE_KEY = 'llms_txt';

    public function __invoke(): Response
    {
        $body = Cache::rememberForever(self::CACHE_KEY, fn (): string => $this->build());

        return response($body)->header('Content-Type', 'text/plain; charset=utf-8');
    }

    private function build(): string
    {
        $lines = [
            '# Jefferson Gonçalves',
            '',
            '> Full-stack developer (Laravel, Livewire, Filament). Maintainer of open-source '
                .'Filament plugins, Laravel packages and starter kits — all MIT-licensed, with CI, '
                .'tests and regular releases.',
            '',
            'Developer portfolio and an actively maintained open-source catalogue. Every page is '
                .'available in English, Portuguese (pt-BR) and Spanish.',
            '',
            '## Pages',
        ];

        foreach ([
            ['home', 'Home', 'overview, featured work and live stats'],
            ['about', 'About', 'background, experience and working principles'],
            ['projects.index', 'Projects', 'searchable catalogue of plugins, packages and starter kits'],
            ['articles.index', 'Articles', 'technical writing and notes'],
            ['links.index', 'Links', 'curated external resources, sites and channels'],
            ['open-source', 'Open Source', 'maintained packages and contribution stats'],
            ['stack', 'Stack', 'the technologies powering this site'],
            ['sponsors', 'Sponsors', 'GitHub Sponsors and supporters'],
        ] as [$route, $label, $note]) {
            $lines[] = '- ['.$label.']('.route($route).'): '.$note;
        }

        $lines[] = '';
        $lines[] = '## Open Source Projects';
        foreach (Project::query()->published()->authored()->orderByDesc('stars')->orderBy('name')->get() as $project) {
            $lines[] = $this->projectLine($project);
        }

        $lines[] = '';
        $lines[] = '## Articles';
        foreach (Project::query()->published()->byCategory(ProjectCategory::Article)->orderByDesc('published_at')->get() as $article) {
            $lines[] = $this->projectLine($article);
        }

        return implode("\n", $lines)."\n";
    }

    private function projectLine(Project $project): string
    {
        $title = $project->getTranslation('title', 'en', false);
        $note = is_string($title) && trim($title) !== '' ? ': '.trim($title) : '';

        return '- ['.$project->name.']('.$project->publicUrl().')'.$note;
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ProjectCategory;
use App\Models\Project;

/**
 * Builds the /llms.txt body (llmstxt.org convention). Shared by the
 * `llms:generate` command (writes it to storage/app, which — unlike public/
 * — survives an atomic deploy's release swap) and LlmsTxtController, which
 * just serves that file.
 */
final class LlmsTxtGenerator
{
    public static function build(): string
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
            ['developers.mcp', 'MCP Server', 'how to query this portfolio from an AI assistant via MCP'],
        ] as [$route, $label, $note]) {
            $lines[] = '- ['.$label.']('.route($route).'): '.$note;
        }

        $lines[] = '';
        $lines[] = '## Open Source Projects';
        foreach (Project::query()->published()->authored()->orderByDesc('stars')->orderBy('name')->get() as $project) {
            $lines[] = self::projectLine($project);
        }

        $lines[] = '';
        $lines[] = '## Articles';
        foreach (Project::query()->published()->byCategory(ProjectCategory::Article)->orderByDesc('published_at')->get() as $article) {
            $lines[] = self::projectLine($article);
        }

        return implode("\n", $lines)."\n";
    }

    private static function projectLine(Project $project): string
    {
        $title = $project->getTranslation('title', 'en', false);
        $note = is_string($title) && trim($title) !== '' ? ': '.trim($title) : '';

        return '- ['.$project->name.']('.$project->publicUrl().')'.$note;
    }
}

<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\PackageType;
use App\Enums\ProjectCategory;
use App\Models\Project;
use App\Support\GithubReadme;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use JeffersonGoncalves\HtmlSanitizer\HtmlSanitizer;
use JeffersonGoncalves\NpmReadme\NpmReadme;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Fetches one published page by slug — a project, article or curated link —
 * with its metadata and a plain-text README excerpt, so a client can answer
 * questions about it without a separate HTTP fetch + HTML parse. Excerpt
 * reuses App\Support\GithubReadme's fetch+sanitize pipeline (skips the
 * cosmetic link-rewrite/lazyload steps ProjectShowPage applies for the
 * browser — irrelevant to a text response).
 */
#[Description('Fetch one published page from Jefferson Gonçalves\' portfolio by its URL slug (as returned by search_projects), with metadata and a README excerpt.')]
class GetPageTool extends Tool
{
    public function handle(Request $request): Response
    {
        $slug = trim((string) $request->get('slug', ''));

        if ($slug === '') {
            return Response::error('The "slug" argument is required.');
        }

        $project = Project::query()->published()->where('slug', $slug)->first();

        if (! $project) {
            return Response::error("No published page found with slug \"{$slug}\".");
        }

        $title = $project->getTranslation('title', 'en', false);
        $description = is_string($title) && trim($title) !== '' ? trim($title) : $project->name;

        $lines = [
            '# '.$project->name,
            '',
            $description,
            '',
            'Category: '.$project->category->getLabel(),
            'URL: '.$project->publicUrl(),
        ];

        foreach ([
            'GitHub' => $project->github_url,
            'Packagist' => $project->packagist_url,
            'npm' => $project->npm_url,
            'Docker Hub' => $project->docker_url,
            'Docs' => $project->docs_url,
            'Demo' => $project->demo_url,
        ] as $label => $url) {
            if ($url) {
                $lines[] = $label.': '.$url;
            }
        }

        if ($project->stars > 0) {
            $lines[] = 'Stars: '.$project->stars;
        }

        $downloads = $project->downloads_label ?: ($project->downloads > 0 ? (string) $project->downloads : null);
        if ($downloads !== null) {
            $lines[] = 'Downloads: '.$downloads;
        }

        if ($project->license) {
            $lines[] = 'License: '.$project->license;
        }

        if ($project->package_type && $project->package_type !== PackageType::None) {
            $lines[] = 'Package type: '.$project->package_type->value;
        }

        if ($project->language) {
            $lines[] = 'Primary language: '.$project->language->value;
        }

        if (! empty($project->stack)) {
            $lines[] = 'Stack: '.implode(', ', $project->stack);
        }

        if (! empty($project->topics)) {
            $lines[] = 'Topics: '.implode(', ', $project->topics);
        }

        if ($project->category === ProjectCategory::FilamentPlugin && ! empty($project->versions)) {
            $lines[] = 'Supported Filament versions: '.implode(', ', GithubReadme::sortedVersions($project->versions));
        }

        $excerpt = $this->readmeExcerpt($project);
        if ($excerpt !== null) {
            $lines[] = '';
            $lines[] = '## README';
            $lines[] = $excerpt;
        }

        return Response::text(implode("\n", $lines));
    }

    private function readmeExcerpt(Project $project): ?string
    {
        $html = match (true) {
            (bool) $project->github_url => GithubReadme::fetchHtml($project->github_url),
            (bool) $project->npm_url => NpmReadme::fetchHtml($project->npm_url),
            default => null,
        };

        if ($html === null) {
            return null;
        }

        $text = trim(html_entity_decode(strip_tags(HtmlSanitizer::clean($html)), ENT_QUOTES));
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return mb_substr($text, 0, 3000);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()
                ->description('The page slug, e.g. "jeffersongoncalves-filament-gtag".')
                ->required(),
        ];
    }
}

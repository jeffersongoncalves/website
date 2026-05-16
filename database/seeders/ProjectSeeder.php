<?php

namespace Database\Seeders;

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    private const FEATURED_REPOS = [
        'filakitv5',
        'filament-help-desk',
        'filament-cep-field',
        'filament-documentation',
        'teamkitv5',
        'filament-service-desk',
    ];

    public function run(): void
    {
        $path = $this->resolveJsonPath();
        $data = $path ? $this->loadJson($path) : null;

        if ($data === null) {
            $this->command?->warn('plugins.json not found — skipping ProjectSeeder.');

            return;
        }

        foreach ($this->iterateEntries($data) as $entry) {
            $this->upsert($entry);
        }
    }

    /**
     * @return iterable<int, array{package: string, title: string, category: ProjectCategory, versions: list<string>, stack: list<string>, extra: array<string, mixed>}>
     */
    private function iterateEntries(array $data): iterable
    {
        foreach (array_merge($data['startkit']['featured'] ?? [], $data['startkit']['legacy'] ?? []) as $row) {
            yield [
                'package' => $row['package'],
                'title' => $row['title'],
                'category' => ProjectCategory::StarterKit,
                'versions' => $this->starterKitVersions($row['package']),
                'stack' => ['Laravel', 'Filament'],
                'extra' => [],
            ];
        }

        foreach (array_merge($data['filament']['plugins'] ?? [], $data['filament']['collaborator'] ?? []) as $row) {
            yield [
                'package' => $row['package'],
                'title' => $row['title'],
                'category' => ProjectCategory::FilamentPlugin,
                'versions' => $this->filamentVersions($row),
                'stack' => ['Filament'],
                'extra' => [],
            ];
        }

        foreach ($data['laravel'] ?? [] as $row) {
            yield [
                'package' => $row['package'],
                'title' => $row['title'],
                'category' => ProjectCategory::LaravelPackage,
                'versions' => ['Laravel 10/11/12'],
                'stack' => ['Laravel'],
                'extra' => [],
            ];
        }

        foreach ($data['cli'] ?? [] as $row) {
            yield [
                'package' => $row['package'],
                'title' => $row['title'],
                'category' => ProjectCategory::Tool,
                'versions' => [],
                'stack' => ['CLI', 'PHP'],
                'extra' => [],
            ];
        }

        foreach ($data['jetbrains'] ?? [] as $row) {
            yield [
                'package' => $row['package'],
                'title' => $row['title'],
                'category' => ProjectCategory::Tool,
                'versions' => [],
                'stack' => ['JetBrains', 'IDE'],
                'extra' => ['jetbrainsId' => $row['jetbrainsId'] ?? null],
            ];
        }
    }

    private function upsert(array $entry): void
    {
        [$vendor, $repoName] = explode('/', $entry['package'], 2);
        $githubUrl = "https://github.com/{$vendor}/{$repoName}";
        $isJetBrains = ! empty($entry['extra']['jetbrainsId']);
        $packagistUrl = $isJetBrains ? null : "https://packagist.org/packages/{$entry['package']}";

        $project = Project::query()->firstOrNew(['slug' => $repoName]);

        // Structural fields (always synced from plugins.json — source of truth)
        $project->name = $repoName;
        $project->repo = $repoName;
        $project->category = $entry['category']->value;
        $project->github_url = $githubUrl;
        $project->packagist_url = $packagistUrl;

        if ($isJetBrains) {
            $project->docs_url = "https://plugins.jetbrains.com/plugin/{$entry['extra']['jetbrainsId']}";
        }

        // Default-only fields (set on insert; never overwrite editor changes).
        // versions + stack stay editor-managed after creation so a re-seed can
        // never wipe manual branch_overrides tied to a specific versions list.
        if (! $project->exists) {
            $project->title = ['pt' => $entry['title'], 'en' => $entry['title'], 'es' => $entry['title']];
            $project->versions = $entry['versions'];
            $project->stack = $entry['stack'];
            $project->stars = 0;
            $project->downloads = 0;
            $project->license = 'MIT';
            $project->status = ProjectStatus::Published->value;
            $project->featured = in_array($repoName, self::FEATURED_REPOS, true);
            $project->published_at = now();
        }

        $project->save();
    }

    /**
     * @return list<string>
     */
    private function filamentVersions(array $row): array
    {
        $versions = [];

        foreach (['v3', 'v4', 'v5'] as $v) {
            if (! empty($row[$v])) {
                $versions[] = $v;
            }
        }

        return $versions;
    }

    /**
     * @return list<string>
     */
    private function starterKitVersions(string $package): array
    {
        if (str_contains($package, 'v5') || str_ends_with($package, 'kitv5') || str_ends_with($package, 'basev5')) {
            return ['Filament v5'];
        }
        if (str_contains($package, 'v4') || str_ends_with($package, 'kitv4') || str_ends_with($package, 'basev4')) {
            return ['Filament v4'];
        }
        if (str_contains($package, 'v3') || str_ends_with($package, 'kitv3') || str_ends_with($package, 'basev3') || str_ends_with($package, 'kit')) {
            return ['Filament v3'];
        }

        return [];
    }

    private function resolveJsonPath(): ?string
    {
        $candidates = array_filter([
            env('PLUGINS_JSON_PATH'),
            database_path('data/plugins.json'),
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function loadJson(string $path): ?array
    {
        $raw = file_get_contents($path);

        return $raw ? json_decode($raw, true) : null;
    }
}

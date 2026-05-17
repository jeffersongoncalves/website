<?php

namespace Database\Seeders;

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    private const FEATURED_REPOS = [
        'jeffersongoncalves/filakitv5',
        'jeffersongoncalves/filament-help-desk',
        'jeffersongoncalves/filament-cep-field',
        'jeffersongoncalves/filament-documentation',
        'jeffersongoncalves/teamkitv5',
        'jeffersongoncalves/filament-service-desk',
    ];

    /**
     * Repos where the user is owner/author (no upstream "maintainer" badge).
     */
    private const OWNED_EXTRAS = [
        'filakitphp/installer' => [
            'title' => 'FilaKit Installer',
            'category' => ProjectCategory::Tool,
            'stack' => ['CLI', 'PHP'],
            'versions' => [],
            'no_packagist' => true,
        ],
    ];

    /**
     * Upstream repos the user contributes to / maintains alongside the author.
     * Listed here so the public projects page surfaces them with the
     * "maintainer" badge.
     */
    private const MAINTAINED_EXTRAS = [
        'filamentphp/filament' => ['title' => 'Filament', 'category' => ProjectCategory::Framework, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'laravel/framework' => ['title' => 'Laravel', 'category' => ProjectCategory::Framework, 'stack' => ['Laravel'], 'versions' => []],
        'livewire/livewire' => ['title' => 'Livewire', 'category' => ProjectCategory::Framework, 'stack' => ['Livewire'], 'versions' => []],
        'livewire/flux' => ['title' => 'Flux', 'category' => ProjectCategory::LaravelPackage, 'stack' => ['Livewire'], 'versions' => []],
        'livewire/volt' => ['title' => 'Volt', 'category' => ProjectCategory::LaravelPackage, 'stack' => ['Livewire'], 'versions' => []],
        'livewire/blaze' => ['title' => 'Blaze', 'category' => ProjectCategory::LaravelPackage, 'stack' => ['Livewire'], 'versions' => [], 'no_packagist' => true],
        'secondnetwork/blade-tabler-icons' => ['title' => 'Blade Tabler Icons', 'category' => ProjectCategory::LaravelPackage, 'stack' => ['Laravel'], 'versions' => []],
        'iurygdeoliveira/labSIS-KIT' => ['title' => 'labSIS Kit', 'category' => ProjectCategory::StarterKit, 'stack' => ['Laravel', 'Filament'], 'versions' => []],
        'laravel-zero/awesome-laravel-zero' => ['title' => 'Awesome Laravel Zero', 'category' => ProjectCategory::Tool, 'stack' => ['Laravel Zero', 'CLI'], 'versions' => [], 'no_packagist' => true],
        'wallacemartinss/filament-whatsapp-conector' => ['title' => 'Filament WhatsApp Conector', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'wallacemartinss/filament-icon-picker' => ['title' => 'Filament Icon Picker', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'stechstudio/filament-impersonate' => ['title' => 'Filament Impersonate', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'leandrocfe/filament-ptbr-form-fields' => ['title' => 'Filament PT-BR Form Fields', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'filamentphp/panels' => ['title' => 'Filament Panels', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'filamentphp/forms' => ['title' => 'Filament Forms', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'filamentphp/tables' => ['title' => 'Filament Tables', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'filamentphp/infolists' => ['title' => 'Filament Infolists', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'filamentphp/notifications' => ['title' => 'Filament Notifications', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'filamentphp/widgets' => ['title' => 'Filament Widgets', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'filamentphp/actions' => ['title' => 'Filament Actions', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'filamentphp/support' => ['title' => 'Filament Support', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'filamentphp/schemas' => ['title' => 'Filament Schemas', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'filamentphp/upgrade' => ['title' => 'Filament Upgrade', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'filamentphp/query-builder' => ['title' => 'Filament Query Builder', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'filamentphp/spatie-laravel-tags-plugin' => ['title' => 'Filament Spatie Tags', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'filamentphp/spatie-laravel-media-library-plugin' => ['title' => 'Filament Spatie Media Library', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'filamentphp/spatie-laravel-google-fonts-plugin' => ['title' => 'Filament Spatie Google Fonts', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'filamentphp/spatie-laravel-settings-plugin' => ['title' => 'Filament Spatie Settings', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'filamentphp/spatie-laravel-translatable-plugin' => ['title' => 'Filament Spatie Translatable', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'filamentphp/spark-billing-provider' => ['title' => 'Filament Spark Billing Provider', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'filamentphp/legacy-site' => ['title' => 'Filament Legacy Site', 'category' => ProjectCategory::Tool, 'stack' => ['Filament'], 'versions' => [], 'no_packagist' => true],
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

        foreach (self::OWNED_EXTRAS as $package => $meta) {
            yield [
                'package' => $package,
                'title' => $meta['title'],
                'category' => $meta['category'],
                'versions' => $meta['versions'] ?? [],
                'stack' => $meta['stack'] ?? [],
                'extra' => [
                    'is_maintainer' => false,
                    'no_packagist' => $meta['no_packagist'] ?? false,
                ],
            ];
        }

        foreach (self::MAINTAINED_EXTRAS as $package => $meta) {
            yield [
                'package' => $package,
                'title' => $meta['title'],
                'category' => $meta['category'],
                'versions' => $meta['versions'] ?? [],
                'stack' => $meta['stack'] ?? [],
                'extra' => [
                    'is_maintainer' => true,
                    'no_packagist' => $meta['no_packagist'] ?? false,
                ],
            ];
        }
    }

    private function upsert(array $entry): void
    {
        [$vendor, $repoName] = explode('/', $entry['package'], 2);
        $githubUrl = "https://github.com/{$vendor}/{$repoName}";
        $isJetBrains = ! empty($entry['extra']['jetbrainsId']);
        $noPackagist = ! empty($entry['extra']['no_packagist']);
        $packagistUrl = ($isJetBrains || $noPackagist) ? null : "https://packagist.org/packages/{$entry['package']}";

        // Slug is owner-repo to avoid collisions across vendors that publish a
        // package with the same repo name (e.g. owner-a/foo + owner-b/foo).
        $slug = $vendor.'-'.$repoName;

        // Match by github_url (canonical identifier) so re-seeding always
        // updates the existing row, even when its slug pre-dates the
        // owner-repo convention.
        $project = Project::query()
            ->where('github_url', $githubUrl)
            ->first() ?? new Project;

        $project->slug = $slug;

        // Structural fields (always synced from plugins.json — source of truth)
        $project->name = self::prettifyName($repoName);
        $project->repo = $repoName;
        $project->category = $entry['category']->value;
        $project->github_url = $githubUrl;
        $project->packagist_url = $packagistUrl;

        if ($isJetBrains) {
            $project->docs_url = "https://plugins.jetbrains.com/plugin/{$entry['extra']['jetbrainsId']}";
        }

        // is_maintainer is source-of-truth from the seed when explicitly
        // provided (extras list), otherwise left to the editor.
        if (array_key_exists('is_maintainer', $entry['extra'] ?? [])) {
            $project->is_maintainer = (bool) $entry['extra']['is_maintainer'];
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
            $project->featured = in_array($entry['package'], self::FEATURED_REPOS, true);
            $project->published_at = now();
        }

        $project->save();
    }

    /**
     * Turn a repo name like "filament-cep-field" into "Filament Cep Field"
     * and normalize "Php" → "PHP" as a whole word.
     */
    private static function prettifyName(string $repo): string
    {
        $name = ucwords(str_replace(['-', '_'], ' ', $repo));

        return preg_replace('/\bPhp\b/u', 'PHP', $name) ?? $name;
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

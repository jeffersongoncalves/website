<?php

namespace Database\Seeders;

use App\Enums\PackageType;
use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Jobs\SyncProjectMetricsJob;
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
            'category' => ProjectCategory::LaravelZeroCli,
            'stack' => ['Laravel Zero', 'CLI'],
            'versions' => [],
            'no_packagist' => true,
        ],
        'jeffersongoncalves/cakephp-fractal-transformer-view' => ['title' => 'CakePHP Fractal Transformer View', 'category' => ProjectCategory::CakePhpPackage, 'stack' => ['CakePHP'], 'versions' => []],
        'jeffersongoncalves/cakephp-datatables' => ['title' => 'CakePHP Datatables', 'category' => ProjectCategory::CakePhpPackage, 'stack' => ['CakePHP'], 'versions' => []],
        'jeffersongoncalves/cakephp-utils' => ['title' => 'CakePHP Utils', 'category' => ProjectCategory::CakePhpPackage, 'stack' => ['CakePHP'], 'versions' => []],
        'jeffersongoncalves/cakephp-permission' => ['title' => 'CakePHP Permission', 'category' => ProjectCategory::CakePhpPackage, 'stack' => ['CakePHP'], 'versions' => []],
        'jeffersongoncalves/cakephp-settings' => ['title' => 'CakePHP Settings', 'category' => ProjectCategory::CakePhpPackage, 'stack' => ['CakePHP'], 'versions' => []],
        'jeffersongoncalves/cakephp-utility' => ['title' => 'CakePHP Utility', 'category' => ProjectCategory::CakePhpPackage, 'stack' => ['CakePHP'], 'versions' => []],
        'jeffersongoncalves/cakephp-user-activity' => ['title' => 'CakePHP User Activity', 'category' => ProjectCategory::CakePhpPackage, 'stack' => ['CakePHP'], 'versions' => []],
    ];

    /**
     * Projects the user relies on daily without authoring or maintaining.
     * Surfaced with the "daily driver" badge on the public projects page.
     */
    private const DAILY_DRIVER_EXTRAS = [
        'alpinejs/alpine' => [
            'title' => 'Alpine.js',
            'category' => ProjectCategory::Framework,
            'stack' => ['JavaScript'],
            'versions' => [],
            'no_packagist' => true,
            'npm' => 'alpinejs',
            'docs' => 'https://alpinejs.dev/docs',
        ],
        'tailwindlabs/tailwindcss' => [
            'title' => 'Tailwind CSS',
            'category' => ProjectCategory::Framework,
            'stack' => ['CSS', 'JavaScript'],
            'versions' => [],
            'no_packagist' => true,
            'npm' => 'tailwindcss',
            'docs' => 'https://tailwindcss.com/docs',
        ],
        'wire-elements/modal' => [
            'title' => 'Wire Elements Modal',
            'category' => ProjectCategory::LivewirePackage,
            'stack' => ['Livewire'],
            'versions' => [],
        ],
        'achyutkneupane/filament-log-viewer' => ['title' => 'Filament Log Viewer', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => []],
        'dutchcodingcompany/filament-developer-logins' => ['title' => 'Filament Developer Logins', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => []],
        'laravel/horizon' => ['title' => 'Laravel Horizon', 'category' => ProjectCategory::LaravelPackage, 'stack' => ['Laravel'], 'versions' => []],
        'ralphjsmit/laravel-seo' => ['title' => 'Laravel SEO', 'category' => ProjectCategory::LaravelPackage, 'stack' => ['Laravel'], 'versions' => []],
        'spatie/laravel-sitemap' => ['title' => 'Laravel Sitemap', 'category' => ProjectCategory::LaravelPackage, 'stack' => ['Laravel'], 'versions' => []],
        'spatie/laravel-sluggable' => ['title' => 'Laravel Sluggable', 'category' => ProjectCategory::LaravelPackage, 'stack' => ['Laravel'], 'versions' => []],
        'spatie/laravel-translatable' => ['title' => 'Laravel Translatable', 'category' => ProjectCategory::LaravelPackage, 'stack' => ['Laravel'], 'versions' => []],
        'barryvdh/laravel-debugbar' => ['title' => 'Laravel Debugbar (barryvdh)', 'category' => ProjectCategory::LaravelPackage, 'stack' => ['Laravel'], 'versions' => []],
        'barryvdh/laravel-ide-helper' => ['title' => 'Laravel IDE Helper', 'category' => ProjectCategory::LaravelPackage, 'stack' => ['Laravel'], 'versions' => []],
        'fakerphp/faker' => ['title' => 'Faker', 'category' => ProjectCategory::Tool, 'stack' => ['PHP'], 'versions' => []],
        'larastan/larastan' => ['title' => 'Larastan', 'category' => ProjectCategory::Tool, 'stack' => ['Laravel', 'PHPStan'], 'versions' => []],
        'pestphp/pest' => ['title' => 'Pest', 'category' => ProjectCategory::Tool, 'stack' => ['PHP', 'Testing'], 'versions' => []],
        'pestphp/pest-plugin-laravel' => ['title' => 'Pest Plugin Laravel', 'category' => ProjectCategory::LaravelPackage, 'stack' => ['Laravel', 'Testing'], 'versions' => []],
    ];

    /**
     * Upstream repos the user contributes to / maintains alongside the author.
     * Listed here so the public projects page surfaces them with the
     * "maintainer" badge.
     */
    private const MAINTAINED_EXTRAS = [
        'filamentphp/filament' => ['title' => 'Filament', 'category' => ProjectCategory::Framework, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5'], 'branch_overrides' => ['1.x' => '3.x', '2.x' => '4.x', '3.x' => '5.x'], 'packagist' => 'filament/filament'],
        'laravel/framework' => ['title' => 'Laravel', 'category' => ProjectCategory::Framework, 'stack' => ['Laravel'], 'versions' => []],
        'livewire/livewire' => ['title' => 'Livewire', 'category' => ProjectCategory::Framework, 'stack' => ['Livewire'], 'versions' => []],
        'livewire/flux' => ['title' => 'Flux', 'category' => ProjectCategory::LivewirePackage, 'stack' => ['Livewire'], 'versions' => []],
        'livewire/flux-pro' => ['title' => 'Flux Pro', 'category' => ProjectCategory::LivewirePackage, 'stack' => ['Livewire'], 'versions' => [], 'is_paid' => true, 'no_github' => true, 'docs' => 'https://fluxui.dev/docs'],
        'livewire/volt' => ['title' => 'Volt', 'category' => ProjectCategory::LivewirePackage, 'stack' => ['Livewire'], 'versions' => []],
        'livewire/blaze' => ['title' => 'Blaze', 'category' => ProjectCategory::LivewirePackage, 'stack' => ['Livewire'], 'versions' => []],
        'secondnetwork/blade-tabler-icons' => ['title' => 'Blade Tabler Icons', 'category' => ProjectCategory::LaravelPackage, 'stack' => ['Laravel'], 'versions' => []],
        'iurygdeoliveira/labSIS-KIT' => ['title' => 'labSIS Kit', 'category' => ProjectCategory::StarterKit, 'stack' => ['Laravel', 'Filament'], 'versions' => []],
        'laravel-zero/awesome-laravel-zero' => ['title' => 'Awesome Laravel Zero', 'category' => ProjectCategory::LaravelZeroCli, 'stack' => ['Laravel Zero', 'CLI'], 'versions' => [], 'no_packagist' => true],
        'wallacemartinss/filament-whatsapp-conector' => ['title' => 'Filament WhatsApp Conector', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'wallacemartinss/filament-icon-picker' => ['title' => 'Filament Icon Picker', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'stechstudio/filament-impersonate' => ['title' => 'Filament Impersonate', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'leandrocfe/filament-ptbr-form-fields' => ['title' => 'Filament PT-BR Form Fields', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'filamentphp/panels' => ['title' => 'Filament Panels', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5'], 'branch_overrides' => ['1.x' => '3.x', '2.x' => '4.x', '3.x' => '5.x'], 'packagist' => 'filament/panels'],
        'filamentphp/forms' => ['title' => 'Filament Forms', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5'], 'branch_overrides' => ['1.x' => '3.x', '2.x' => '4.x', '3.x' => '5.x'], 'packagist' => 'filament/forms'],
        'filamentphp/tables' => ['title' => 'Filament Tables', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5'], 'branch_overrides' => ['1.x' => '3.x', '2.x' => '4.x', '3.x' => '5.x'], 'packagist' => 'filament/tables'],
        'filamentphp/infolists' => ['title' => 'Filament Infolists', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5'], 'branch_overrides' => ['1.x' => '3.x', '2.x' => '4.x', '3.x' => '5.x'], 'packagist' => 'filament/infolists'],
        'filamentphp/notifications' => ['title' => 'Filament Notifications', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5'], 'branch_overrides' => ['1.x' => '3.x', '2.x' => '4.x', '3.x' => '5.x'], 'packagist' => 'filament/notifications'],
        'filamentphp/widgets' => ['title' => 'Filament Widgets', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5'], 'branch_overrides' => ['1.x' => '3.x', '2.x' => '4.x', '3.x' => '5.x'], 'packagist' => 'filament/widgets'],
        'filamentphp/actions' => ['title' => 'Filament Actions', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5'], 'branch_overrides' => ['1.x' => '3.x', '2.x' => '4.x', '3.x' => '5.x'], 'packagist' => 'filament/actions'],
        'filamentphp/support' => ['title' => 'Filament Support', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5'], 'branch_overrides' => ['1.x' => '3.x', '2.x' => '4.x', '3.x' => '5.x'], 'packagist' => 'filament/support'],
        'filamentphp/schemas' => ['title' => 'Filament Schemas', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5'], 'branch_overrides' => ['1.x' => '3.x', '2.x' => '4.x', '3.x' => '5.x'], 'packagist' => 'filament/schemas'],
        'filamentphp/upgrade' => ['title' => 'Filament Upgrade', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5'], 'branch_overrides' => ['1.x' => '3.x', '2.x' => '4.x', '3.x' => '5.x'], 'packagist' => 'filament/upgrade'],
        'filamentphp/query-builder' => ['title' => 'Filament Query Builder', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5'], 'branch_overrides' => ['1.x' => '3.x', '2.x' => '4.x', '3.x' => '5.x'], 'packagist' => 'filament/query-builder'],
        'filamentphp/spatie-laravel-tags-plugin' => ['title' => 'Filament Spatie Tags', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5'], 'branch_overrides' => ['1.x' => '3.x', '2.x' => '4.x', '3.x' => '5.x'], 'packagist' => 'filament/spatie-laravel-tags-plugin'],
        'filamentphp/spatie-laravel-media-library-plugin' => ['title' => 'Filament Spatie Media Library', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5'], 'branch_overrides' => ['1.x' => '3.x', '2.x' => '4.x', '3.x' => '5.x'], 'packagist' => 'filament/spatie-laravel-media-library-plugin'],
        'filamentphp/spatie-laravel-google-fonts-plugin' => ['title' => 'Filament Spatie Google Fonts', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5'], 'branch_overrides' => ['1.x' => '3.x', '2.x' => '4.x', '3.x' => '5.x'], 'packagist' => 'filament/spatie-laravel-google-fonts-plugin'],
        'filamentphp/spatie-laravel-settings-plugin' => ['title' => 'Filament Spatie Settings', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5'], 'branch_overrides' => ['1.x' => '3.x', '2.x' => '4.x', '3.x' => '5.x'], 'packagist' => 'filament/spatie-laravel-settings-plugin'],
        'filamentphp/spatie-laravel-translatable-plugin' => ['title' => 'Filament Spatie Translatable', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5'], 'branch_overrides' => ['1.x' => '3.x', '2.x' => '4.x', '3.x' => '5.x'], 'packagist' => 'filament/spatie-laravel-translatable-plugin'],
        'filamentphp/spark-billing-provider' => ['title' => 'Filament Spark Billing Provider', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5'], 'branch_overrides' => ['1.x' => '3.x', '2.x' => '4.x', '3.x' => '5.x'], 'packagist' => 'filament/spark-billing-provider'],
        'filamentphp/legacy-site' => ['title' => 'Filament Legacy Site', 'category' => ProjectCategory::Tool, 'stack' => ['Filament'], 'versions' => [], 'no_packagist' => true],
        'fruitcake/laravel-debugbar' => ['title' => 'Laravel Debugbar', 'category' => ProjectCategory::LaravelPackage, 'stack' => ['Laravel'], 'versions' => []],
        'php-debugbar/php-debugbar' => ['title' => 'PHP Debugbar', 'category' => ProjectCategory::LaravelPackage, 'stack' => ['PHP'], 'versions' => []],
        'bezhanSalleh/filament-shield' => ['title' => 'Filament Shield', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'leandrocfe/filament-apex-charts' => ['title' => 'Filament Apex Charts', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'lara-zeus/zeus' => ['title' => 'Lara Zeus', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'CodeWithDennis/filament-simple-map' => ['title' => 'Filament Simple Map', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'lukas-frey/filament-icon-picker' => ['title' => 'Filament Icon Picker (Lukas)', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'TappNetwork/Filament-Help-Article' => ['title' => 'Filament Help Article', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        '4nuunes/filament-communicate' => ['title' => 'Filament Communicate', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'AlizHarb/laravel-modular-themer-tester' => ['title' => 'Laravel Modular Themer Tester', 'category' => ProjectCategory::LaravelPackage, 'stack' => ['Laravel'], 'versions' => []],
        'wallacemartinss/website_template' => ['title' => 'Website Template', 'category' => ProjectCategory::StarterKit, 'stack' => ['Laravel'], 'versions' => [], 'no_packagist' => true],
        'andrefelipe18/filament-webpush' => ['title' => 'Filament WebPush', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'TappNetwork/filament-footer-package' => ['title' => 'Filament Footer', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'dietercoopman/laravel-dashboard-laravelnews-tile' => ['title' => 'Laravel Dashboard Laravel News Tile', 'category' => ProjectCategory::LaravelPackage, 'stack' => ['Laravel'], 'versions' => []],
        'filaship/filaship' => ['title' => 'Filaship', 'category' => ProjectCategory::Framework, 'stack' => ['Filament'], 'versions' => []],
        'barraroot/filament_material_theme' => ['title' => 'Filament Material Theme', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'andrefelipe18/laradumps-filament' => ['title' => 'LaraDumps Filament', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'andrefelipe18/tallstackui-filament' => ['title' => 'TallStackUI Filament', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'wallacemartinss/Infra-com-Traefik' => ['title' => 'Infra com Traefik', 'category' => ProjectCategory::Tool, 'stack' => ['Docker', 'Traefik'], 'versions' => [], 'no_packagist' => true],
        'wallacemartinss/core_tenant' => ['title' => 'Core Tenant', 'category' => ProjectCategory::LaravelPackage, 'stack' => ['Laravel'], 'versions' => [], 'no_packagist' => true],
        'dvarilek/filament-table-select' => ['title' => 'Filament Table Select', 'category' => ProjectCategory::FilamentPlugin, 'stack' => ['Filament'], 'versions' => ['v3', 'v4', 'v5']],
        'alessandronuunes/AiHub' => ['title' => 'AiHub', 'category' => ProjectCategory::Tool, 'stack' => ['PHP'], 'versions' => [], 'no_packagist' => true],
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

        foreach ($data['filament']['plugins'] ?? [] as $row) {
            yield [
                'package' => $row['package'],
                'title' => $row['title'],
                'category' => ProjectCategory::FilamentPlugin,
                'versions' => $this->filamentVersions($row),
                'stack' => ['Filament'],
                'extra' => [
                    'is_maintainer' => false,
                ],
            ];
        }

        // Collaborator entries are upstream plugins the user maintains alongside
        // the author — flag them as maintainer so they get the badge + show up
        // in the maintained count.
        foreach ($data['filament']['collaborator'] ?? [] as $row) {
            yield [
                'package' => $row['package'],
                'title' => $row['title'],
                'category' => ProjectCategory::FilamentPlugin,
                'versions' => $this->filamentVersions($row),
                'stack' => ['Filament'],
                'extra' => [
                    'is_maintainer' => true,
                ],
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
                'category' => ProjectCategory::LaravelZeroCli,
                'versions' => [],
                'stack' => ['Laravel Zero', 'CLI'],
                'extra' => [],
            ];
        }

        foreach ($data['jetbrains'] ?? [] as $row) {
            yield [
                'package' => $row['package'],
                'title' => $row['title'],
                'category' => ProjectCategory::IdePlugin,
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
                    'packagist' => $meta['packagist'] ?? null,
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
                    'is_maintainer' => empty($meta['is_paid']),
                    'is_paid' => $meta['is_paid'] ?? false,
                    'no_packagist' => $meta['no_packagist'] ?? false,
                    'no_github' => $meta['no_github'] ?? false,
                    'packagist' => $meta['packagist'] ?? null,
                    'docs' => $meta['docs'] ?? null,
                    'branch_overrides' => $meta['branch_overrides'] ?? null,
                ],
            ];
        }

        foreach (self::DAILY_DRIVER_EXTRAS as $package => $meta) {
            yield [
                'package' => $package,
                'title' => $meta['title'],
                'category' => $meta['category'],
                'versions' => $meta['versions'] ?? [],
                'stack' => $meta['stack'] ?? [],
                'extra' => [
                    'is_maintainer' => false,
                    'is_daily_driver' => true,
                    'no_packagist' => $meta['no_packagist'] ?? false,
                    'packagist' => $meta['packagist'] ?? null,
                    'npm' => $meta['npm'] ?? null,
                    'docs' => $meta['docs'] ?? null,
                ],
            ];
        }
    }

    private function upsert(array $entry): void
    {
        [$vendor, $repoName] = explode('/', $entry['package'], 2);
        $noGithub = ! empty($entry['extra']['no_github']);
        $githubUrl = $noGithub ? null : "https://github.com/{$vendor}/{$repoName}";
        $isJetBrains = ! empty($entry['extra']['jetbrainsId']);
        $noPackagist = ! empty($entry['extra']['no_packagist']);
        $packagistPackage = ! empty($entry['extra']['packagist']) ? $entry['extra']['packagist'] : $entry['package'];
        $packagistUrl = ($isJetBrains || $noPackagist) ? null : "https://packagist.org/packages/{$packagistPackage}";

        // Slug is owner-repo to avoid collisions across vendors that publish a
        // package with the same repo name (e.g. owner-a/foo + owner-b/foo).
        $slug = $vendor.'-'.$repoName;

        // Match by github_url (canonical identifier) so re-seeding always
        // updates the existing row, even when its slug pre-dates the
        // owner-repo convention. For repos without a public github
        // (paid packages) fall back to matching by the slug we computed.
        $project = $githubUrl
            ? (Project::query()->where('github_url', $githubUrl)->first() ?? new Project)
            : (Project::query()->where('slug', $slug)->first() ?? new Project);

        $project->slug = $slug;

        // Structural fields (always synced from plugins.json — source of truth)
        $project->name = self::prettifyName($repoName);
        $project->repo = $repoName;
        $project->category = $entry['category']->value;
        $project->github_url = $githubUrl;
        $project->packagist_url = $packagistUrl;
        $project->package_type = self::resolvePackageType($entry, $isJetBrains, $noPackagist)->value;

        if ($isJetBrains) {
            $project->docs_url = "https://plugins.jetbrains.com/plugin/{$entry['extra']['jetbrainsId']}";
        }

        // is_maintainer + is_daily_driver are source-of-truth from the seed
        // when explicitly provided (extras list), otherwise left to the editor.
        if (array_key_exists('is_maintainer', $entry['extra'] ?? [])) {
            $project->is_maintainer = (bool) $entry['extra']['is_maintainer'];
        }

        if (array_key_exists('is_daily_driver', $entry['extra'] ?? [])) {
            $project->is_daily_driver = (bool) $entry['extra']['is_daily_driver'];
        }

        if (array_key_exists('is_paid', $entry['extra'] ?? [])) {
            $project->is_paid = (bool) $entry['extra']['is_paid'];
        }

        // Branch overrides — seed-managed when explicitly provided so re-seeds
        // can correct a stale 3.x → 3.x mapping (filamentphp v5 lives on main).
        if (! empty($entry['extra']['branch_overrides']) && is_array($entry['extra']['branch_overrides'])) {
            $project->branch_overrides = $entry['extra']['branch_overrides'];
        }

        // Optional npm + extra docs URL overrides for extras (e.g. JS packages
        // that publish only on npm, or projects whose docs live off-repo).
        if (! empty($entry['extra']['npm'])) {
            $project->npm_url = 'https://www.npmjs.com/package/'.$entry['extra']['npm'];
        }
        if (! empty($entry['extra']['docs'])) {
            $project->docs_url = $entry['extra']['docs'];
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

        // Force a metrics sync for every seeded project so freshly added rows
        // and pre-existing rows whose synced fields didn't change still pick
        // up GitHub stars, Packagist downloads and the user's contribution
        // count without waiting for the daily scheduler.
        SyncProjectMetricsJob::dispatch($project);
    }

    /**
     * Decide which manifest the metrics sync should read for this project:
     * `composer.json` for Composer/Packagist packages, `package.json` for npm
     * packages, or `none` for repos that don't publish either (JetBrains
     * plugins, infra repos, paid packages without a public source).
     */
    private static function resolvePackageType(array $entry, bool $isJetBrains, bool $noPackagist): PackageType
    {
        if ($isJetBrains) {
            return PackageType::JetBrains;
        }

        if (! empty($entry['extra']['npm']) && $noPackagist) {
            return PackageType::Npm;
        }

        if ($noPackagist) {
            return PackageType::None;
        }

        return PackageType::Composer;
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
        $path = database_path('data/plugins.json');

        return is_file($path) ? $path : null;
    }

    private function loadJson(string $path): ?array
    {
        $raw = file_get_contents($path);

        return $raw ? json_decode($raw, true) : null;
    }
}

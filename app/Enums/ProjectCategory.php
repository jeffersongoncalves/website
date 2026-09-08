<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * To add a new category: add the `case` below, add its translation key to
 * lang/{pt_BR,en,es}/admin.php (enums.category.*), and add its arm to
 * meta() further down. That's the whole checklist — every other method here
 * reads from meta().
 */
enum ProjectCategory: string implements HasColor, HasLabel
{
    case FilamentPlugin = 'filament_plugin';
    case LaravelPackage = 'laravel_package';
    case LivewirePackage = 'livewire_package';
    case CakePhpPackage = 'cakephp_package';
    case LaravelZeroCli = 'laravel_zero_cli';
    case IdePlugin = 'ide_plugin';
    case PhpPackage = 'php_package';
    case JavascriptPackage = 'javascript_package';
    case Framework = 'framework';
    case CssFramework = 'css_framework';
    case StarterKit = 'starter_kit';
    case Saas = 'saas';
    case Tool = 'tool';
    case Application = 'application';
    case LearningResource = 'learning_resource';
    case AwesomeList = 'awesome_list';
    case MobileLibrary = 'mobile_library';
    case Docker = 'docker';
    case Database = 'database';
    case Website = 'website';
    case YoutubeChannel = 'youtube_channel';
    case Article = 'article';

    /**
     * Single source of truth per category — label, badge color, catalogue
     * family, and (for external reference links only) the /links hub section
     * anchor. Adding a category means one line here, not four separate
     * match() arms scattered through the file. Still a match() (not a plain
     * keyed array) so PHPStan keeps flagging a missing arm when a new case is
     * added — the exhaustiveness check that made the old 4-match version safe.
     *
     * `linksSection: null` means the category lives in the code catalogue
     * (/projects) rather than the external-links hub — see isExternalLink().
     * `family: null` applies to external links and articles, which have no
     * catalogue family filter.
     *
     * @return array{label: string, color: string, family: ?ProjectFamily, linksSection: ?string}
     */
    private function meta(): array
    {
        return match ($this) {
            self::FilamentPlugin => ['label' => 'admin.enums.category.filament_plugin', 'color' => 'warning', 'family' => ProjectFamily::PhpLaravel, 'linksSection' => null],
            self::LaravelPackage => ['label' => 'admin.enums.category.laravel_package', 'color' => 'danger', 'family' => ProjectFamily::PhpLaravel, 'linksSection' => null],
            self::LivewirePackage => ['label' => 'admin.enums.category.livewire_package', 'color' => 'success', 'family' => ProjectFamily::PhpLaravel, 'linksSection' => null],
            self::CakePhpPackage => ['label' => 'admin.enums.category.cakephp_package', 'color' => 'info', 'family' => ProjectFamily::PhpLaravel, 'linksSection' => null],
            self::LaravelZeroCli => ['label' => 'admin.enums.category.laravel_zero_cli', 'color' => 'warning', 'family' => ProjectFamily::PhpLaravel, 'linksSection' => null],
            self::IdePlugin => ['label' => 'admin.enums.category.ide_plugin', 'color' => 'gray', 'family' => ProjectFamily::PhpLaravel, 'linksSection' => null],
            self::PhpPackage => ['label' => 'admin.enums.category.php_package', 'color' => 'info', 'family' => ProjectFamily::PhpLaravel, 'linksSection' => null],
            self::JavascriptPackage => ['label' => 'admin.enums.category.javascript_package', 'color' => 'warning', 'family' => ProjectFamily::JsCss, 'linksSection' => null],
            self::Framework => ['label' => 'admin.enums.category.framework', 'color' => 'primary', 'family' => ProjectFamily::JsCss, 'linksSection' => null],
            self::CssFramework => ['label' => 'admin.enums.category.css_framework', 'color' => 'info', 'family' => ProjectFamily::JsCss, 'linksSection' => null],
            self::MobileLibrary => ['label' => 'admin.enums.category.mobile_library', 'color' => 'success', 'family' => ProjectFamily::JsCss, 'linksSection' => null],
            self::StarterKit => ['label' => 'admin.enums.category.starter_kit', 'color' => 'success', 'family' => ProjectFamily::AppsTools, 'linksSection' => null],
            self::Saas => ['label' => 'admin.enums.category.saas', 'color' => 'info', 'family' => ProjectFamily::AppsTools, 'linksSection' => null],
            self::Tool => ['label' => 'admin.enums.category.tool', 'color' => 'gray', 'family' => ProjectFamily::AppsTools, 'linksSection' => null],
            self::Application => ['label' => 'admin.enums.category.application', 'color' => 'gray', 'family' => ProjectFamily::AppsTools, 'linksSection' => null],
            self::Docker => ['label' => 'admin.enums.category.docker', 'color' => 'info', 'family' => ProjectFamily::AppsTools, 'linksSection' => null],
            self::Database => ['label' => 'admin.enums.category.database', 'color' => 'success', 'family' => ProjectFamily::AppsTools, 'linksSection' => null],
            self::Website => ['label' => 'admin.enums.category.website', 'color' => 'primary', 'family' => null, 'linksSection' => 'sites'],
            self::YoutubeChannel => ['label' => 'admin.enums.category.youtube_channel', 'color' => 'danger', 'family' => null, 'linksSection' => 'watch'],
            self::LearningResource => ['label' => 'admin.enums.category.learning_resource', 'color' => 'success', 'family' => null, 'linksSection' => 'learn'],
            self::AwesomeList => ['label' => 'admin.enums.category.awesome_list', 'color' => 'primary', 'family' => null, 'linksSection' => 'lists'],
            self::Article => ['label' => 'admin.enums.category.article', 'color' => 'info', 'family' => null, 'linksSection' => null],
        };
    }

    public function getLabel(): string
    {
        return __($this->meta()['label']);
    }

    public function getColor(): string
    {
        return $this->meta()['color'];
    }

    /**
     * Coarse grouping for the /projects category filter. External links and
     * articles have no catalogue family.
     */
    public function family(): ?ProjectFamily
    {
        return $this->meta()['family'];
    }

    /**
     * The /links hub section anchor (and per-section paginator page name) for
     * an external-link category — used to scroll back to the right section
     * when returning from a detail page. Null for non-external categories.
     */
    public function linksSection(): ?string
    {
        return $this->meta()['linksSection'];
    }

    /**
     * External reference links — sites, YouTube channels, learning resources
     * and awesome lists. These carry no stars/downloads and live on the /links
     * hub, NOT in the code catalogue at /projects.
     */
    public function isExternalLink(): bool
    {
        return $this->linksSection() !== null;
    }

    /**
     * @return list<self>
     */
    public static function externalLinkCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $c): bool => $c->isExternalLink()));
    }

    /**
     * The code catalogue (/projects) — every category that is neither an
     * external reference link nor an article.
     *
     * @return list<self>
     */
    public static function catalogueCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $c): bool => $c !== self::Article && ! $c->isExternalLink(),
        ));
    }
}

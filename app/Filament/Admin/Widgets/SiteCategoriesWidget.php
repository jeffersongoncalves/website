<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Support\SiteStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SiteCategoriesWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = null;

    protected function getHeading(): ?string
    {
        return __('admin.widgets.site_categories.heading');
    }

    protected function getStats(): array
    {
        $s = SiteStats::all();
        $desc = __('admin.widgets.site_categories.desc');

        $stats = [];

        foreach (self::CATEGORY_COLORS as $key => $color) {
            $stats[] = Stat::make(
                __("admin.widgets.site_categories.{$key}"),
                number_format($s[$key], 0, ',', '.'),
            )
                ->description($desc)
                ->color($color);
        }

        return $stats;
    }

    /**
     * Stat key (also the SiteStats key and label suffix) => Filament color, in
     * display order. Mirrors ProjectCategory's case order and getColor() —
     * Article is the one enum case with no stat tile, so it's excluded.
     *
     * @var array<string, string>
     */
    private const CATEGORY_COLORS = [
        'filament' => 'warning',
        'laravel' => 'danger',
        'livewire' => 'success',
        'cakephp' => 'info',
        'laravel_zero' => 'warning',
        'ide_plugin' => 'gray',
        'php_package' => 'info',
        'javascript_package' => 'warning',
        'framework' => 'primary',
        'css_framework' => 'info',
        'starter' => 'success',
        'saas' => 'info',
        'tool' => 'gray',
        'application' => 'gray',
        'learning_resource' => 'success',
        'awesome_list' => 'primary',
        'mobile_library' => 'success',
        'docker' => 'info',
        'database' => 'success',
        'website' => 'primary',
        'youtube_channel' => 'danger',
    ];

    protected function getColumns(): int
    {
        return 5;
    }
}

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

        return [
            Stat::make(__('admin.widgets.site_categories.filament'), number_format($s['filament'], 0, ',', '.'))
                ->description($desc)
                ->color('warning'),

            Stat::make(__('admin.widgets.site_categories.laravel'), number_format($s['laravel'], 0, ',', '.'))
                ->description($desc)
                ->color('danger'),

            Stat::make(__('admin.widgets.site_categories.livewire'), number_format($s['livewire'], 0, ',', '.'))
                ->description($desc)
                ->color('success'),

            Stat::make(__('admin.widgets.site_categories.cakephp'), number_format($s['cakephp'], 0, ',', '.'))
                ->description($desc)
                ->color('info'),

            Stat::make(__('admin.widgets.site_categories.laravel_zero'), number_format($s['laravel_zero'], 0, ',', '.'))
                ->description($desc)
                ->color('warning'),

            Stat::make(__('admin.widgets.site_categories.ide_plugin'), number_format($s['ide_plugin'], 0, ',', '.'))
                ->description($desc)
                ->color('gray'),

            Stat::make(__('admin.widgets.site_categories.php_package'), number_format($s['php_package'], 0, ',', '.'))
                ->description($desc)
                ->color('info'),

            Stat::make(__('admin.widgets.site_categories.javascript_package'), number_format($s['javascript_package'], 0, ',', '.'))
                ->description($desc)
                ->color('warning'),

            Stat::make(__('admin.widgets.site_categories.framework'), number_format($s['framework'], 0, ',', '.'))
                ->description($desc)
                ->color('primary'),

            Stat::make(__('admin.widgets.site_categories.css_framework'), number_format($s['css_framework'], 0, ',', '.'))
                ->description($desc)
                ->color('info'),

            Stat::make(__('admin.widgets.site_categories.starter'), number_format($s['starter'], 0, ',', '.'))
                ->description($desc)
                ->color('success'),

            Stat::make(__('admin.widgets.site_categories.saas'), number_format($s['saas'], 0, ',', '.'))
                ->description($desc)
                ->color('info'),

            Stat::make(__('admin.widgets.site_categories.tool'), number_format($s['tool'], 0, ',', '.'))
                ->description($desc)
                ->color('gray'),

            Stat::make(__('admin.widgets.site_categories.application'), number_format($s['application'], 0, ',', '.'))
                ->description($desc)
                ->color('gray'),

            Stat::make(__('admin.widgets.site_categories.learning_resource'), number_format($s['learning_resource'], 0, ',', '.'))
                ->description($desc)
                ->color('success'),

            Stat::make(__('admin.widgets.site_categories.awesome_list'), number_format($s['awesome_list'], 0, ',', '.'))
                ->description($desc)
                ->color('primary'),

            Stat::make(__('admin.widgets.site_categories.mobile_library'), number_format($s['mobile_library'], 0, ',', '.'))
                ->description($desc)
                ->color('success'),

            Stat::make(__('admin.widgets.site_categories.docker'), number_format($s['docker'], 0, ',', '.'))
                ->description($desc)
                ->color('info'),

            Stat::make(__('admin.widgets.site_categories.database'), number_format($s['database'], 0, ',', '.'))
                ->description($desc)
                ->color('success'),

            Stat::make(__('admin.widgets.site_categories.website'), number_format($s['website'], 0, ',', '.'))
                ->description($desc)
                ->color('primary'),

            Stat::make(__('admin.widgets.site_categories.youtube_channel'), number_format($s['youtube_channel'], 0, ',', '.'))
                ->description($desc)
                ->color('danger'),
        ];
    }

    protected function getColumns(): int
    {
        return 5;
    }
}

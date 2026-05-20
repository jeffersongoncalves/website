<?php

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

            Stat::make(__('admin.widgets.site_categories.framework'), number_format($s['framework'], 0, ',', '.'))
                ->description($desc)
                ->color('primary'),

            Stat::make(__('admin.widgets.site_categories.starter'), number_format($s['starter'], 0, ',', '.'))
                ->description($desc)
                ->color('success'),

            Stat::make(__('admin.widgets.site_categories.saas'), number_format($s['saas'], 0, ',', '.'))
                ->description($desc)
                ->color('info'),

            Stat::make(__('admin.widgets.site_categories.tool'), number_format($s['tool'], 0, ',', '.'))
                ->description($desc)
                ->color('gray'),

            Stat::make(__('admin.widgets.site_categories.docker'), number_format($s['docker'], 0, ',', '.'))
                ->description($desc)
                ->color('info'),

            Stat::make(__('admin.widgets.site_categories.database'), number_format($s['database'], 0, ',', '.'))
                ->description($desc)
                ->color('success'),
        ];
    }

    protected function getColumns(): int
    {
        return 5;
    }
}

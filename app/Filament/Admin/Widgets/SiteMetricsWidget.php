<?php

namespace App\Filament\Admin\Widgets;

use App\Support\SiteStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SiteMetricsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = null;

    protected function getHeading(): ?string
    {
        return __('admin.widgets.site_metrics.heading');
    }

    protected function getStats(): array
    {
        $s = SiteStats::all();

        return [
            Stat::make(__('admin.widgets.site_metrics.repos'), number_format($s['repos'], 0, ',', '.'))
                ->description(__('admin.widgets.site_metrics.repos_desc'))
                ->color('primary'),

            Stat::make(__('admin.widgets.site_metrics.stars'), number_format($s['stars'], 0, ',', '.'))
                ->description('★ '.__('admin.widgets.site_metrics.stars_desc'))
                ->color('primary'),

            Stat::make(__('admin.widgets.site_metrics.followers'), $this->compact($s['followers']))
                ->description(__('admin.widgets.site_metrics.followers_desc')),

            Stat::make(__('admin.widgets.site_metrics.sponsors'), number_format($s['public_sponsors'], 0, ',', '.'))
                ->description(__('admin.widgets.site_metrics.sponsors_desc'))
                ->color('primary'),

            Stat::make(__('admin.widgets.site_metrics.contributions'), number_format($s['contributions']['total'], 0, ',', '.'))
                ->description(__('admin.widgets.site_metrics.contributions_desc'))
                ->color('success'),

            Stat::make(__('admin.widgets.site_metrics.maintained'), number_format($s['maintained'], 0, ',', '.'))
                ->description(__('admin.widgets.site_metrics.maintained_desc'))
                ->color('success'),

            Stat::make(__('admin.widgets.site_metrics.daily_drivers'), number_format($s['daily_drivers'], 0, ',', '.'))
                ->description(__('admin.widgets.site_metrics.daily_drivers_desc'))
                ->color('info'),
        ];
    }

    protected function getColumns(): int
    {
        return 5;
    }

    private function compact(int $n): string
    {
        if ($n >= 1_000_000) {
            return rtrim(rtrim(number_format($n / 1_000_000, 1, '.', ''), '0'), '.').'M';
        }
        if ($n >= 1_000) {
            return rtrim(rtrim(number_format($n / 1_000, 1, '.', ''), '0'), '.').'k';
        }

        return (string) $n;
    }
}

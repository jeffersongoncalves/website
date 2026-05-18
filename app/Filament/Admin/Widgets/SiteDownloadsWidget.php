<?php

namespace App\Filament\Admin\Widgets;

use App\Support\SiteStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SiteDownloadsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = null;

    protected function getHeading(): ?string
    {
        return __('admin.widgets.site_downloads.heading');
    }

    protected function getStats(): array
    {
        $s = SiteStats::all();

        return [
            Stat::make(__('admin.widgets.site_downloads.total'), $this->compact($s['downloads']))
                ->description('↓ '.__('admin.widgets.site_downloads.total_desc'))
                ->color('success'),

            Stat::make(__('admin.widgets.site_downloads.packagist'), $this->compact($s['downloads_packagist']))
                ->description('↓ '.__('admin.widgets.site_downloads.packagist_desc'))
                ->color('success'),

            Stat::make(__('admin.widgets.site_downloads.npm'), $this->compact($s['downloads_npm']))
                ->description('↓ '.__('admin.widgets.site_downloads.npm_desc'))
                ->color('success'),

            Stat::make(__('admin.widgets.site_downloads.jetbrains'), $this->compact($s['downloads_jetbrains']))
                ->description('↓ '.__('admin.widgets.site_downloads.jetbrains_desc'))
                ->color('success'),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
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

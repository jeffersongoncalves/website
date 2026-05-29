<?php

namespace App\Filament\Admin\Widgets;

use App\Support\SiteStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SiteTopicsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = null;

    protected function getHeading(): ?string
    {
        return __('admin.widgets.site_topics.heading');
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $desc = __('admin.widgets.site_topics.desc');

        return collect(SiteStats::all()['topics'])
            ->take(15)
            ->map(fn (array $row): Stat => Stat::make(
                '#'.$row['topic'],
                number_format($row['total'], 0, ',', '.'),
            )->description($desc)->color('gray'))
            ->all();
    }

    protected function getColumns(): int
    {
        return 5;
    }

    public static function canView(): bool
    {
        return SiteStats::all()['topics'] !== [];
    }
}

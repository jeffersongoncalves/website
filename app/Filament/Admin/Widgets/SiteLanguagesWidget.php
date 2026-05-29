<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\ProjectLanguage;
use App\Support\SiteStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SiteLanguagesWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = null;

    protected function getHeading(): ?string
    {
        return __('admin.widgets.site_languages.heading');
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $desc = __('admin.widgets.site_languages.desc');

        return collect(SiteStats::all()['languages'])
            ->take(12)
            ->map(function (array $row) use ($desc): Stat {
                $enum = ProjectLanguage::tryFrom($row['language']);

                return Stat::make($enum?->getLabel() ?? $row['language'], number_format($row['total'], 0, ',', '.'))
                    ->description($desc)
                    ->color($enum?->getColor() ?? 'gray');
            })
            ->all();
    }

    protected function getColumns(): int
    {
        return 6;
    }

    public static function canView(): bool
    {
        // Hide until at least one language has been captured.
        return SiteStats::all()['languages'] !== [];
    }
}

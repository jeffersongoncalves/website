<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Project;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProjectImportsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 6;

    protected ?string $heading = null;

    protected function getHeading(): ?string
    {
        return __('admin.widgets.project_imports.heading');
    }

    protected function getStats(): array
    {
        $weekStart = now()->startOfWeek();
        $lastWeekStart = $weekStart->copy()->subWeek();

        $newThisWeek = Project::query()->where('created_at', '>=', $weekStart)->count();
        $newLastWeek = Project::query()->whereBetween('created_at', [$lastWeekStart, $weekStart])->count();
        $delta = $newThisWeek - $newLastWeek;
        $deltaLabel = ($delta >= 0 ? '+' : '').number_format($delta, 0, ',', '.');

        $starredTotal = Project::query()->whereNotNull('starred_at')->count();
        $starredThisWeek = Project::query()
            ->whereNotNull('starred_at')
            ->where('created_at', '>=', $weekStart)
            ->count();

        $total = Project::query()->count();

        return [
            Stat::make(__('admin.widgets.project_imports.new_week'), number_format($newThisWeek, 0, ',', '.'))
                ->description(__('admin.widgets.project_imports.delta', ['delta' => $deltaLabel]))
                ->descriptionIcon($delta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($delta >= 0 ? 'success' : 'danger')
                ->chart(self::perDayCounts(14)),

            Stat::make(__('admin.widgets.project_imports.starred_total'), number_format($starredTotal, 0, ',', '.'))
                ->description(__('admin.widgets.project_imports.starred_week', ['count' => number_format($starredThisWeek, 0, ',', '.')]))
                ->descriptionIcon('heroicon-m-star')
                ->color('warning'),

            Stat::make(__('admin.widgets.project_imports.total'), number_format($total, 0, ',', '.'))
                ->description(__('admin.widgets.project_imports.total_desc'))
                ->color('primary'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }

    /**
     * Count of projects created on each of the last $days days (oldest first),
     * grouped in PHP so we stay portable across sqlite/mysql/postgres. Floats
     * because Stat::chart() expects a numeric (float) sparkline series.
     *
     * @return list<float>
     */
    private static function perDayCounts(int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $byDay = Project::query()
            ->where('created_at', '>=', $start)
            ->pluck('created_at')
            ->countBy(fn ($date) => $date->format('Y-m-d'));

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $out[] = (float) $byDay->get(now()->subDays($i)->format('Y-m-d'), 0);
        }

        return $out;
    }
}

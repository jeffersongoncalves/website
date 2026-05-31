<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Project;
use Filament\Widgets\ChartWidget;

class ProjectsPerDayChart extends ChartWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('admin.widgets.projects_per_day.heading');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $days = 30;
        $start = now()->subDays($days - 1)->startOfDay();

        // Pull just the two timestamps for the window and bucket in PHP — keeps
        // the date grouping portable across sqlite/mysql/postgres.
        $rows = Project::query()
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'starred_at']);

        $allByDay = [];
        $starredByDay = [];

        foreach ($rows as $row) {
            $key = $row->created_at->format('Y-m-d');
            $allByDay[$key] = ($allByDay[$key] ?? 0) + 1;

            if ($row->starred_at !== null) {
                $starredByDay[$key] = ($starredByDay[$key] ?? 0) + 1;
            }
        }

        $labels = [];
        $all = [];
        $starred = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $key = $date->format('Y-m-d');
            $labels[] = $date->format('d/m');
            $all[] = $allByDay[$key] ?? 0;
            $starred[] = $starredByDay[$key] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => __('admin.widgets.projects_per_day.all'),
                    'data' => $all,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.5)',
                    'borderColor' => '#f59e0b',
                ],
                [
                    'label' => __('admin.widgets.projects_per_day.starred'),
                    'data' => $starred,
                    'backgroundColor' => 'rgba(234, 179, 8, 0.8)',
                    'borderColor' => '#eab308',
                ],
            ],
            'labels' => $labels,
        ];
    }
}

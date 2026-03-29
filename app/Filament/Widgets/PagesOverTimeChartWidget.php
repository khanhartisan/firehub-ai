<?php

namespace App\Filament\Widgets;

use App\Models\Page;
use App\Models\Snapshot;
use Filament\Widgets\ChartWidget;

class PagesOverTimeChartWidget extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Activity (last 14 days)';

    public function updatedFilter(): void
    {
        $this->cachedData = null;
        $this->updateChartData();
    }

    protected ?string $description = 'New pages and snapshots created per day';

    protected ?string $maxHeight = '300px';

    public ?string $filter = '14days';

    /**
     * @return array<scalar, scalar> | null
     */
    protected function getFilters(): ?array
    {
        return [
            '7days' => 'Last 7 days',
            '14days' => 'Last 14 days',
            '30days' => 'Last 30 days',
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $days = match ($this->filter) {
            '7days' => 7,
            '30days' => 30,
            default => 14,
        };

        $labels = [];
        $pagesData = [];
        $snapshotsData = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateStr = $date->format('M j');
            $labels[] = $dateStr;

            $pagesData[] = Page::query()
                ->whereDate('created_at', $date)
                ->count();

            $snapshotsData[] = Snapshot::query()
                ->whereDate('created_at', $date)
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Pages',
                    'data' => $pagesData,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Snapshots',
                    'data' => $snapshotsData,
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }
}

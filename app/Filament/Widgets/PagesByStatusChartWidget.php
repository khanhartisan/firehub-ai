<?php

namespace App\Filament\Widgets;

use App\Enums\ScrapingStatus;
use App\Models\Page;
use Filament\Widgets\ChartWidget;

class PagesByStatusChartWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Pages by scraping status';

    protected ?string $description = 'Distribution of pages by their current scraping status';

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $counts = Page::query()
            ->selectRaw('scraping_status, count(*) as count')
            ->groupBy('scraping_status')
            ->pluck('count', 'scraping_status')
            ->all();

        $labels = [];
        $data = [];
        $colors = [
            '#94a3b8', // PENDING - slate
            '#3b82f6', // QUEUED - blue
            '#f59e0b', // FETCHING - amber
            '#22c55e', // SUCCESS - green
            '#ef4444', // FAILED - red
            '#f97316', // TIMEOUT - orange
            '#6b7280', // BLOCKED - gray
        ];

        foreach (ScrapingStatus::cases() as $i => $status) {
            $labels[] = $status->getLabel() ?? $status->name;
            $data[] = $counts[$status->value] ?? 0;
        }

        $backgroundColor = array_slice($colors, 0, count($labels));

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $backgroundColor,
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }
}

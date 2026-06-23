<?php

namespace App\Filament\Widgets;

use App\Enums\PublicationStatus;
use App\Models\Publication;
use Filament\Widgets\ChartWidget;

class PublicationsByStatusChartWidget extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Publications by status';

    protected ?string $description = 'Distribution of publications across the publishing pipeline';

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
        $counts = Publication::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $labels = [];
        $data = [];
        $colors = [
            '#94a3b8', // AWAITING
            '#3b82f6', // PENDING
            '#f59e0b', // PUBLISHING
            '#22c55e', // PUBLISHED
            '#f97316', // TIMEOUT
            '#ef4444', // FAILED
            '#dc2626', // ERROR
        ];

        foreach (PublicationStatus::cases() as $status) {
            $labels[] = $status->name;
            $data[] = $counts[$status->value] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => array_slice($colors, 0, count($labels)),
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }
}

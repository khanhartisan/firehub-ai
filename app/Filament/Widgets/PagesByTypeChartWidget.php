<?php

namespace App\Filament\Widgets;

use App\Enums\ScrapableType;
use App\Models\Page;
use Filament\Widgets\ChartWidget;

class PagesByTypeChartWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Pages by scrapable type';

    protected ?string $description = 'Content type distribution';

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $counts = Page::query()
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->all();

        $labels = [];
        $data = [];

        foreach (ScrapableType::cases() as $type) {
            $labels[] = $type->getLabel() ?? $type->name;
            $data[] = $counts[$type->value] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Pages',
                    'data' => $data,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.6)',
                    'borderColor' => 'rgb(245, 158, 11)',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $labels,
        ];
    }
}

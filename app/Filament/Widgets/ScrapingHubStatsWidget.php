<?php

namespace App\Filament\Widgets;

use App\Models\Page;
use App\Models\Snapshot;
use App\Models\Source;
use App\Models\Vertical;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ScrapingHubStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Overview';

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $verticalsCount = Vertical::query()->count();
        $sourcesCount = Source::query()->count();
        $pagesCount = Page::query()->count();
        $snapshotsCount = Snapshot::query()->count();

        $pagesPerDay = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $pagesPerDay[] = Page::query()
                ->whereDate('created_at', $date)
                ->count();
        }

        return [
            Stat::make('Verticals', $verticalsCount)
                ->description('Content categories')
                ->descriptionIcon(Heroicon::OutlinedRectangleStack)
                ->color('primary'),
            Stat::make('Sources', $sourcesCount)
                ->description('Base URLs to scrape')
                ->descriptionIcon(Heroicon::OutlinedLink)
                ->color('success'),
            Stat::make('Pages', $pagesCount)
                ->description('Tracked URLs (last 7 days trend)')
                ->descriptionIcon(Heroicon::OutlinedDocumentText)
                ->chart($pagesPerDay)
                ->color('info'),
            Stat::make('Snapshots', $snapshotsCount)
                ->description('Content versions stored')
                ->descriptionIcon(Heroicon::OutlinedPhoto)
                ->color('warning'),
        ];
    }
}

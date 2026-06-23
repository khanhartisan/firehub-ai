<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Publication;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DistributionStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Distribution';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $clientsCount = Client::query()->count();
        $articlesCount = Article::query()->count();
        $channelsCount = Channel::query()->count();
        $publicationsCount = Publication::query()->count();

        $articlesPerDay = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $articlesPerDay[] = Article::query()
                ->whereDate('created_at', $date)
                ->count();
        }

        return [
            Stat::make('Clients', $clientsCount)
                ->description('Publishing tenants')
                ->descriptionIcon(Heroicon::OutlinedWindow)
                ->color('primary'),
            Stat::make('Articles', $articlesCount)
                ->description('Content pipeline (last 7 days trend)')
                ->descriptionIcon(Heroicon::OutlinedNewspaper)
                ->chart($articlesPerDay)
                ->color('success'),
            Stat::make('Channels', $channelsCount)
                ->description('Publishing endpoints')
                ->descriptionIcon(Heroicon::OutlinedSignal)
                ->color('info'),
            Stat::make('Publications', $publicationsCount)
                ->description('Article-to-channel deliveries')
                ->descriptionIcon(Heroicon::OutlinedPaperAirplane)
                ->color('warning'),
        ];
    }
}

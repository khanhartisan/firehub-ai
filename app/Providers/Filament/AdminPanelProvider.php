<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\DistributionStatsWidget;
use App\Filament\Widgets\PagesByStatusChartWidget;
use App\Filament\Widgets\PagesByTypeChartWidget;
use App\Filament\Widgets\PagesOverTimeChartWidget;
use App\Filament\Widgets\PublicationsByStatusChartWidget;
use App\Filament\Widgets\ScrapingHubStatsWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName(config('app.name'))
            ->login()
            ->navigationGroups([
                1 => 'Content',
                2 => 'Remote',
                3 => 'Distribution',
                4 => 'HITL',
                5 => 'Administration',
            ])
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                ScrapingHubStatsWidget::class,
                DistributionStatsWidget::class,
                PagesByStatusChartWidget::class,
                PublicationsByStatusChartWidget::class,
                PagesByTypeChartWidget::class,
                PagesOverTimeChartWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}

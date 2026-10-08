<?php

namespace Weba\ProductionPriority\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Weba\ProductionPriority\Services\ProductionOverview;
use Weba\ProductionPriority\Services\ProductionStatsCalculator;

class ProductionStatsWidget extends StatsOverviewWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected static function getPagePermission(): ?string
    {
        return 'widget_production_priority_production_stats_widget';
    }

    protected function getHeading(): ?string
    {
        return __('production-priority::filament/production-priority.dashboard.stats.heading');
    }

    protected function getStats(): array
    {
        $stats = app(ProductionOverview::class)->stats();

        return [
            Stat::make(__('production-priority::filament/production-priority.dashboard.stats.open-orders'), $stats['total'])
                ->description(__('production-priority::filament/production-priority.dashboard.stats.open-orders-hint'))
                ->icon('heroicon-o-clipboard-document-list')
                ->color('primary'),

            Stat::make(__('production-priority::filament/production-priority.dashboard.stats.late'), $stats['late'])
                ->description(__('production-priority::filament/production-priority.dashboard.stats.late-hint'))
                ->icon('heroicon-o-exclamation-triangle')
                ->color($stats['late'] > 0 ? 'danger' : 'success'),

            Stat::make(__('production-priority::filament/production-priority.dashboard.stats.due-soon'), $stats['due_soon'])
                ->description(__('production-priority::filament/production-priority.dashboard.stats.due-soon-hint', ['days' => ProductionStatsCalculator::DUE_SOON_DAYS]))
                ->icon('heroicon-o-clock')
                ->color($stats['due_soon'] > 0 ? 'warning' : 'success'),

            Stat::make(__('production-priority::filament/production-priority.dashboard.stats.average-progress'), number_format($stats['average_progress'], 1).'%')
                ->description(__('production-priority::filament/production-priority.dashboard.stats.average-progress-hint'))
                ->icon('heroicon-o-chart-bar')
                ->color('info'),

            Stat::make(__('production-priority::filament/production-priority.dashboard.stats.material-short'), $stats['material_short'])
                ->description(__('production-priority::filament/production-priority.dashboard.stats.material-short-hint'))
                ->icon('heroicon-o-cube')
                ->color($stats['material_short'] > 0 ? 'warning' : 'success'),
        ];
    }
}

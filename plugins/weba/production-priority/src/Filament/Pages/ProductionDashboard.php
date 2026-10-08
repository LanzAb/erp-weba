<?php

namespace Weba\ProductionPriority\Filament\Pages;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;
use Weba\ProductionPriority\Filament\Widgets\LateOrdersWidget;
use Weba\ProductionPriority\Filament\Widgets\OrdersByStatusChart;
use Weba\ProductionPriority\Filament\Widgets\PriorityRankingWidget;
use Weba\ProductionPriority\Filament\Widgets\ProductionStatsWidget;
use Weba\ProductionPriority\Filament\Widgets\WorkCenterLoadWidget;
use Webkul\Support\Enums\NavigationGroup;

class ProductionDashboard extends BaseDashboard
{
    use HasPageShield;

    protected static string $routePath = 'production-priority';

    protected static function getPagePermission(): ?string
    {
        return 'page_production_priority_production_dashboard';
    }

    public static function getNavigationLabel(): string
    {
        return __('production-priority::filament/production-priority.dashboard.navigation.title');
    }

    public static function getNavigationGroup(): string|\UnitEnum
    {
        return NavigationGroup::Dashboard;
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('production-priority::filament/production-priority.dashboard.navigation.title');
    }

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'lg'      => 2,
        ];
    }

    public function getWidgets(): array
    {
        return [
            ProductionStatsWidget::class,
            OrdersByStatusChart::class,
            WorkCenterLoadWidget::class,
            PriorityRankingWidget::class,
            LateOrdersWidget::class,
        ];
    }
}

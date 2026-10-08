<?php

namespace Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\OrderMonitoringResource\Pages\ListOrderMonitoring;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\OrderMonitoringResource\Tables\OrderMonitoringTable;
use Weba\ProductionPriority\Services\EvaluationService;
use Weba\ProductionPriority\Services\OrderProgressService;
use Webkul\Manufacturing\Enums\ManufacturingOrderState;
use Webkul\Manufacturing\Models\Order;

/**
 * Read-only monitoring view over the open manufacturing orders. Access follows
 * the permissions of the manufacturing order itself.
 */
class OrderMonitoringResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?int $navigationSort = 2;

    protected static ?string $cluster = ProductionPriority::class;

    protected static ?string $slug = 'monitoring';

    public static function getModelLabel(): string
    {
        return __('production-priority::filament/production-priority.monitoring.model-label');
    }

    public static function getNavigationLabel(): string
    {
        return __('production-priority::filament/production-priority.monitoring.navigation.title');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return OrderMonitoringTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('state', array_map(
                fn (ManufacturingOrderState $state): string => $state->value,
                EvaluationService::EVALUATED_STATES,
            ))
            ->with(['product', 'priorityAttribute', ...OrderProgressService::RELATIONS]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrderMonitoring::route('/'),
        ];
    }
}

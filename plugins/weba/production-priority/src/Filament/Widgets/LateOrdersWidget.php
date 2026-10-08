<?php

namespace Weba\ProductionPriority\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Weba\ProductionPriority\Services\EvaluationService;
use Weba\ProductionPriority\Services\OrderProgressService;
use Weba\ProductionPriority\Support\OrderProgress;
use Webkul\Manufacturing\Enums\ManufacturingOrderState;
use Webkul\Manufacturing\Filament\Clusters\Operations\Resources\ManufacturingOrderResource;
use Webkul\Manufacturing\Models\Order;

/**
 * Open orders whose deadline has passed, the most overdue first.
 */
class LateOrdersWidget extends TableWidget
{
    use HasWidgetShield;

    public const LIMIT = 10;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static function getPagePermission(): ?string
    {
        return 'widget_production_priority_late_orders_widget';
    }

    protected function getTableHeading(): string|Htmlable|null
    {
        return __('production-priority::filament/production-priority.dashboard.late.heading', ['limit' => self::LIMIT]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Order::query()
                ->whereIn('state', array_map(
                    fn (ManufacturingOrderState $state): string => $state->value,
                    EvaluationService::EVALUATED_STATES,
                ))
                ->whereNotNull('deadline_at')
                ->where('deadline_at', '<', now())
                ->with(['product', ...OrderProgressService::RELATIONS])
                ->orderBy('deadline_at')
                ->limit(self::LIMIT))
            ->columns([
                TextColumn::make('name')
                    ->label(__('production-priority::filament/production-priority.monitoring.table.order')),

                TextColumn::make('product.name')
                    ->label(__('production-priority::filament/production-priority.monitoring.table.product'))
                    ->placeholder('—'),

                TextColumn::make('state')
                    ->label(__('production-priority::filament/production-priority.monitoring.table.state'))
                    ->badge(),

                TextColumn::make('deadline_at')
                    ->label(__('production-priority::filament/production-priority.monitoring.table.deadline'))
                    ->dateTime(),

                TextColumn::make('days_late')
                    ->label(__('production-priority::filament/production-priority.dashboard.late.days-late'))
                    ->state(fn (Order $record): string => number_format(abs($this->progress($record)->daysToDeadline ?? 0), 1))
                    ->suffix(' '.__('production-priority::filament/production-priority.dashboard.late.days'))
                    ->badge()
                    ->color('danger'),

                TextColumn::make('progress_percent')
                    ->label(__('production-priority::filament/production-priority.monitoring.table.progress'))
                    ->state(fn (Order $record): float => $this->progress($record)->progressPercent)
                    ->numeric(decimalPlaces: 0)
                    ->suffix('%'),

                TextColumn::make('priority')
                    ->label(__('production-priority::filament/production-priority.monitoring.table.priority'))
                    ->badge(),
            ])
            ->recordUrl(fn (Order $record): string => ManufacturingOrderResource::getUrl('view', ['record' => $record]))
            ->paginated(false)
            ->poll('60s')
            ->emptyStateHeading(__('production-priority::filament/production-priority.dashboard.late.empty-heading'))
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    protected function progress(Order $order): OrderProgress
    {
        return app(OrderProgressService::class)->forOrder($order);
    }
}

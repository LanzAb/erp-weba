<?php

namespace Weba\ProductionPriority\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Webkul\Manufacturing\Enums\ManufacturingOrderState;
use Webkul\Manufacturing\Models\Order;

class OrdersByStatusChart extends ChartWidget
{
    use HasWidgetShield;

    public const DONE_WINDOW_DAYS = 30;

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '30s';

    protected ?string $maxHeight = '260px';

    protected int|string|array $columnSpan = 1;

    protected static function getPagePermission(): ?string
    {
        return 'widget_production_priority_orders_by_status_chart';
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('production-priority::filament/production-priority.dashboard.status-chart.heading');
    }

    public function getDescription(): string|Htmlable|null
    {
        return __('production-priority::filament/production-priority.dashboard.status-chart.description', ['days' => self::DONE_WINDOW_DAYS]);
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $states = [
            ManufacturingOrderState::DRAFT,
            ManufacturingOrderState::CONFIRMED,
            ManufacturingOrderState::PROGRESS,
            ManufacturingOrderState::TO_CLOSE,
            ManufacturingOrderState::DONE,
        ];

        $counts = Order::query()
            ->selectRaw('state, COUNT(*) as total')
            ->whereIn('state', array_map(fn (ManufacturingOrderState $state): string => $state->value, $states))
            ->where(function ($query): void {
                $query->where('state', '!=', ManufacturingOrderState::DONE->value)
                    ->orWhere('finished_at', '>=', now()->subDays(self::DONE_WINDOW_DAYS));
            })
            ->groupBy('state')
            ->pluck('total', 'state');

        return [
            'datasets' => [
                [
                    'data'            => array_map(fn (ManufacturingOrderState $state): int => (int) ($counts[$state->value] ?? 0), $states),
                    'backgroundColor' => ['#9ca3af', '#f59e0b', '#3b82f6', '#06b6d4', '#22c55e'],
                ],
            ],
            'labels' => array_map(fn (ManufacturingOrderState $state): string => $state->getLabel(), $states),
        ];
    }
}

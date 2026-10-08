<?php

namespace Weba\ProductionPriority\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Weba\ProductionPriority\Services\EvaluationService;
use Webkul\Manufacturing\Enums\ManufacturingOrderState;
use Webkul\Manufacturing\Enums\WorkOrderState;
use Webkul\Manufacturing\Models\WorkCenter;
use Webkul\Manufacturing\Models\WorkOrder;

/**
 * Work still to be done per work center. It shows the workload only; it is not
 * compared with the capacity of the work center.
 */
class WorkCenterLoadWidget extends ChartWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 3;

    protected ?string $pollingInterval = '30s';

    protected ?string $maxHeight = '260px';

    protected int|string|array $columnSpan = 1;

    protected static function getPagePermission(): ?string
    {
        return 'widget_production_priority_work_center_load_widget';
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('production-priority::filament/production-priority.dashboard.work-center-load.heading');
    }

    public function getDescription(): string|Htmlable|null
    {
        return __('production-priority::filament/production-priority.dashboard.work-center-load.description');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $minutes = WorkOrder::query()
            ->selectRaw('work_center_id, SUM(CASE WHEN expected_duration > COALESCE(duration, 0) THEN expected_duration - COALESCE(duration, 0) ELSE 0 END) as remaining')
            ->whereNotIn('state', [WorkOrderState::DONE->value, WorkOrderState::CANCEL->value])
            ->whereHas('manufacturingOrder', fn ($query) => $query->whereIn('state', array_map(
                fn (ManufacturingOrderState $state): string => $state->value,
                EvaluationService::EVALUATED_STATES,
            )))
            ->groupBy('work_center_id')
            ->pluck('remaining', 'work_center_id')
            ->filter(fn ($remaining): bool => (float) $remaining > 0)
            ->sortDesc();

        $names = WorkCenter::query()->whereIn('id', $minutes->keys())->pluck('name', 'id');

        return [
            'datasets' => [
                [
                    'label'           => __('production-priority::filament/production-priority.dashboard.work-center-load.dataset'),
                    'data'            => $minutes->map(fn ($remaining): float => round((float) $remaining / 60, 1))->values()->all(),
                    'backgroundColor' => '#3b82f6',
                ],
            ],
            'labels' => $minutes->keys()->map(fn ($id): string => (string) ($names[$id] ?? '#'.$id))->all(),
        ];
    }
}

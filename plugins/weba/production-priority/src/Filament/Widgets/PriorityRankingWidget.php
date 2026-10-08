<?php

namespace Weba\ProductionPriority\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\EvaluationResource;
use Weba\ProductionPriority\Models\Evaluation;
use Weba\ProductionPriority\Models\EvaluationItem;
use Weba\ProductionPriority\Services\OrderProgressService;
use Weba\ProductionPriority\Support\OrderProgress;
use Webkul\Manufacturing\Filament\Clusters\Operations\Resources\ManufacturingOrderResource;

/**
 * Top orders of the latest SAW evaluation.
 */
class PriorityRankingWidget extends TableWidget
{
    use HasWidgetShield;

    public const LIMIT = 10;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static function getPagePermission(): ?string
    {
        return 'widget_production_priority_priority_ranking_widget';
    }

    /**
     * Must not touch the database: Filament Shield builds the table of every
     * widget to read its label, also before the plugin tables exist.
     */
    protected function getTableHeading(): string|Htmlable|null
    {
        return __('production-priority::filament/production-priority.dashboard.ranking.heading', ['limit' => self::LIMIT]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(function (): ?string {
                $evaluation = $this->latestEvaluation();

                return $evaluation
                    ? __('production-priority::filament/production-priority.dashboard.ranking.evaluation', [
                        'name' => $evaluation->name,
                        'date' => $evaluation->evaluated_at->format('d M Y H:i'),
                    ])
                    : null;
            })
            ->query(fn () => EvaluationItem::query()
                ->where('evaluation_id', $this->latestEvaluation()?->id ?? 0)
                ->with(['order.product', ...array_map(fn (string $relation): string => 'order.'.$relation, OrderProgressService::RELATIONS)])
                ->orderBy('rank')
                ->limit(self::LIMIT))
            ->columns([
                TextColumn::make('rank')
                    ->label(__('production-priority::filament/production-priority.evaluation.infolist.ranking.rank'))
                    ->badge()
                    ->color('primary'),

                TextColumn::make('order.name')
                    ->label(__('production-priority::filament/production-priority.monitoring.table.order'))
                    ->placeholder('—'),

                TextColumn::make('order.product.name')
                    ->label(__('production-priority::filament/production-priority.monitoring.table.product'))
                    ->placeholder('—'),

                TextColumn::make('score')
                    ->label(__('production-priority::filament/production-priority.evaluation.infolist.ranking.score'))
                    ->numeric(decimalPlaces: 4),

                TextColumn::make('time_status')
                    ->label(__('production-priority::filament/production-priority.monitoring.table.time-status'))
                    ->state(fn (EvaluationItem $record): string => $this->timeStatus($this->progress($record)))
                    ->badge()
                    ->color(fn (EvaluationItem $record): string => $this->timeStatusColor($this->progress($record))),

                TextColumn::make('progress_percent')
                    ->label(__('production-priority::filament/production-priority.monitoring.table.progress'))
                    ->state(fn (EvaluationItem $record): ?float => $this->progress($record)?->progressPercent)
                    ->numeric(decimalPlaces: 0)
                    ->suffix('%')
                    ->placeholder('—'),

                TextColumn::make('applied')
                    ->label(__('production-priority::filament/production-priority.evaluation.infolist.ranking.priority'))
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state
                        ? __('production-priority::filament/production-priority.evaluation.infolist.ranking.urgent')
                        : '—')
                    ->color(fn (mixed $state): string => $state ? 'danger' : 'gray'),
            ])
            ->recordUrl(fn (EvaluationItem $record): ?string => $record->order
                ? ManufacturingOrderResource::getUrl('view', ['record' => $record->order])
                : null)
            ->paginated(false)
            ->poll('60s')
            ->emptyStateHeading(__('production-priority::filament/production-priority.dashboard.ranking.empty-heading'))
            ->emptyStateDescription(__('production-priority::filament/production-priority.dashboard.ranking.empty-description'))
            ->emptyStateIcon('heroicon-o-trophy')
            ->emptyStateActions([
                Action::make('openEvaluations')
                    ->label(__('production-priority::filament/production-priority.dashboard.ranking.open-evaluations'))
                    ->url(EvaluationResource::getUrl('index'))
                    ->icon('heroicon-o-arrow-right'),
            ]);
    }

    protected function latestEvaluation(): ?Evaluation
    {
        return once(fn (): ?Evaluation => Evaluation::query()->latest('evaluated_at')->latest('id')->first());
    }

    protected function progress(EvaluationItem $item): ?OrderProgress
    {
        return $item->order ? app(OrderProgressService::class)->forOrder($item->order) : null;
    }

    protected function timeStatus(?OrderProgress $progress): string
    {
        if ($progress === null || $progress->daysToDeadline === null) {
            return __('production-priority::filament/production-priority.monitoring.time.no-deadline');
        }

        $days = number_format(abs($progress->daysToDeadline), 1);

        return $progress->isLate
            ? __('production-priority::filament/production-priority.monitoring.time.late', ['days' => $days])
            : __('production-priority::filament/production-priority.monitoring.time.left', ['days' => $days]);
    }

    protected function timeStatusColor(?OrderProgress $progress): string
    {
        return match (true) {
            $progress === null || $progress->daysToDeadline === null => 'gray',
            $progress->isLate                                        => 'danger',
            $progress->daysToDeadline <= 3                           => 'warning',
            default                                                  => 'success',
        };
    }
}

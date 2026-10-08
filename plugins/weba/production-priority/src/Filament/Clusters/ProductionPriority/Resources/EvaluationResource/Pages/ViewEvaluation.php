<?php

namespace Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\EvaluationResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\EvaluationResource;
use Weba\ProductionPriority\Models\Evaluation;
use Weba\ProductionPriority\Services\PriorityApplier;

class ViewEvaluation extends ViewRecord
{
    protected static string $resource = EvaluationResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        return static::getResource()::getEloquentQuery()
            ->with(['items.order.product'])
            ->findOrFail($key);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->applyAction(),
            $this->revertAction(),
        ];
    }

    protected function applyAction(): Action
    {
        return Action::make('applyPriority')
            ->label(__('production-priority::filament/production-priority.evaluation.actions.apply.label'))
            ->icon('heroicon-o-bolt')
            ->color('danger')
            ->visible(fn (Evaluation $record): bool => ! $record->isApplied() && Gate::allows('update', $record))
            ->modalHeading(__('production-priority::filament/production-priority.evaluation.actions.apply.heading'))
            ->modalDescription(__('production-priority::filament/production-priority.evaluation.actions.apply.description'))
            ->schema([
                TextInput::make('top_n')
                    ->label(__('production-priority::filament/production-priority.evaluation.actions.apply.top-n'))
                    ->numeric()
                    ->integer()
                    ->required()
                    ->minValue(1)
                    ->maxValue(fn (Evaluation $record): int => max(1, $record->order_count))
                    ->default(3),

                Textarea::make('reason')
                    ->label(__('production-priority::filament/production-priority.evaluation.actions.apply.reason'))
                    ->rows(2),
            ])
            ->action(function (array $data, Evaluation $record): void {
                try {
                    $changed = app(PriorityApplier::class)->apply(
                        $record,
                        (int) $data['top_n'],
                        filled($data['reason'] ?? null) ? $data['reason'] : null,
                    );
                } catch (LogicException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('production-priority::filament/production-priority.evaluation.actions.apply.success', ['count' => $changed]))
                    ->send();

                $this->redirect(EvaluationResource::getUrl('view', ['record' => $record]));
            });
    }

    protected function revertAction(): Action
    {
        return Action::make('revertPriority')
            ->label(__('production-priority::filament/production-priority.evaluation.actions.revert.label'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(__('production-priority::filament/production-priority.evaluation.actions.revert.description'))
            ->visible(fn (Evaluation $record): bool => $record->isApplied() && Gate::allows('update', $record))
            ->action(function (Evaluation $record): void {
                try {
                    $restored = app(PriorityApplier::class)->revert($record);
                } catch (LogicException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('production-priority::filament/production-priority.evaluation.actions.revert.success', ['count' => $restored]))
                    ->send();

                $this->redirect(EvaluationResource::getUrl('view', ['record' => $record]));
            });
    }
}

<?php

namespace Weba\ProductionPriority\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Weba\ProductionPriority\Models\Criterion;
use Weba\ProductionPriority\Services\CriterionWeightNormalizer;

class NormalizeWeightsAction
{
    public static function make(): Action
    {
        return Action::make('normalizeWeights')
            ->label(__('production-priority::filament/production-priority.criterion.actions.normalize.label'))
            ->icon('heroicon-o-scale')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(__('production-priority::filament/production-priority.criterion.actions.normalize.description'))
            ->visible(fn (): bool => Gate::allows('update', new Criterion))
            ->action(function (): void {
                $criteria = Criterion::query()->active()->orderBy('sort')->get();

                try {
                    $weights = app(CriterionWeightNormalizer::class)->normalize(
                        $criteria->mapWithKeys(fn (Criterion $criterion): array => [$criterion->id => (float) $criterion->weight])->all()
                    );
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(__('production-priority::filament/production-priority.criterion.actions.normalize.failed'))
                        ->body($exception->getMessage())
                        ->send();

                    return;
                }

                DB::transaction(function () use ($criteria, $weights): void {
                    foreach ($criteria as $criterion) {
                        $criterion->update(['weight' => $weights[$criterion->id]]);
                    }
                });

                Notification::make()
                    ->success()
                    ->title(__('production-priority::filament/production-priority.criterion.actions.normalize.success'))
                    ->send();
            });
    }
}

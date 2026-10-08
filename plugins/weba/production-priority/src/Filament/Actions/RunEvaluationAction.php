<?php

namespace Weba\ProductionPriority\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Component;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\EvaluationResource;
use Weba\ProductionPriority\Models\Evaluation;
use Weba\ProductionPriority\Services\EvaluationService;

class RunEvaluationAction
{
    public static function make(): Action
    {
        return Action::make('runEvaluation')
            ->label(__('production-priority::filament/production-priority.evaluation.actions.run.label'))
            ->icon('heroicon-o-play')
            ->color('primary')
            ->visible(fn (): bool => Gate::allows('create', Evaluation::class))
            ->modalHeading(__('production-priority::filament/production-priority.evaluation.actions.run.heading'))
            ->modalDescription(__('production-priority::filament/production-priority.evaluation.actions.run.description'))
            ->modalSubmitActionLabel(__('production-priority::filament/production-priority.evaluation.actions.run.submit'))
            ->schema([
                TextInput::make('name')
                    ->label(__('production-priority::filament/production-priority.evaluation.actions.run.name'))
                    ->placeholder(__('production-priority::filament/production-priority.evaluation.actions.run.name-placeholder'))
                    ->maxLength(255),

                Textarea::make('notes')
                    ->label(__('production-priority::filament/production-priority.evaluation.actions.run.notes'))
                    ->rows(2),
            ])
            ->action(function (array $data, Component $livewire): void {
                try {
                    $evaluation = app(EvaluationService::class)->run(
                        name: filled($data['name'] ?? null) ? $data['name'] : null,
                        notes: filled($data['notes'] ?? null) ? $data['notes'] : null,
                    );
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(__('production-priority::filament/production-priority.evaluation.actions.run.failed'))
                        ->body($exception->getMessage())
                        ->persistent()
                        ->send();

                    return;
                }

                if ($evaluation === null) {
                    Notification::make()
                        ->warning()
                        ->title(__('production-priority::filament/production-priority.evaluation.actions.run.nothing-to-rank'))
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('production-priority::filament/production-priority.evaluation.actions.run.success'))
                    ->body(__('production-priority::filament/production-priority.evaluation.actions.run.success-body', ['count' => $evaluation->order_count]))
                    ->send();

                $livewire->redirect(EvaluationResource::getUrl('view', ['record' => $evaluation]));
            });
    }
}

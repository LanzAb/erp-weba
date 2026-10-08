<?php

namespace Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\EvaluationResource\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Weba\ProductionPriority\Enums\EvaluationTrigger;
use Weba\ProductionPriority\Models\Evaluation;

class EvaluationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('production-priority::filament/production-priority.evaluation.table.name'))
                    ->searchable(),

                TextColumn::make('evaluated_at')
                    ->label(__('production-priority::filament/production-priority.evaluation.table.evaluated-at'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('trigger')
                    ->label(__('production-priority::filament/production-priority.evaluation.table.trigger'))
                    ->badge(),

                TextColumn::make('order_count')
                    ->label(__('production-priority::filament/production-priority.evaluation.table.order-count'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('evaluator.name')
                    ->label(__('production-priority::filament/production-priority.evaluation.table.evaluator'))
                    ->placeholder('—'),

                IconColumn::make('applied')
                    ->label(__('production-priority::filament/production-priority.evaluation.table.applied'))
                    ->state(fn (Evaluation $record): bool => $record->isApplied())
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('trigger')
                    ->label(__('production-priority::filament/production-priority.evaluation.table.trigger'))
                    ->options(EvaluationTrigger::options()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('evaluated_at', 'desc');
    }
}

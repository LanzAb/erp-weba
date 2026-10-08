<?php

namespace Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\CriterionResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CriteriaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('production-priority::filament/production-priority.criterion.table.code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('production-priority::filament/production-priority.criterion.table.name'))
                    ->searchable(),

                TextColumn::make('type')
                    ->label(__('production-priority::filament/production-priority.criterion.table.type'))
                    ->badge(),

                TextColumn::make('source')
                    ->label(__('production-priority::filament/production-priority.criterion.table.source')),

                TextColumn::make('weight')
                    ->label(__('production-priority::filament/production-priority.criterion.table.weight'))
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('production-priority::filament/production-priority.criterion.table.is-active'))
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort')
            ->defaultSort('sort');
    }
}

<?php

namespace Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\EvaluationResource\Pages\ListEvaluations;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\EvaluationResource\Pages\ViewEvaluation;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\EvaluationResource\Schemas\EvaluationInfolist;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\EvaluationResource\Tables\EvaluationsTable;
use Weba\ProductionPriority\Models\Evaluation;

class EvaluationResource extends Resource
{
    protected static ?string $model = Evaluation::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static ?int $navigationSort = 3;

    protected static ?string $cluster = ProductionPriority::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('production-priority::models/evaluation.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('production-priority::filament/production-priority.evaluation.navigation.title');
    }

    public static function infolist(Schema $schema): Schema
    {
        return EvaluationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EvaluationsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['evaluator', 'applier']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvaluations::route('/'),
            'view'  => ViewEvaluation::route('/{record}'),
        ];
    }
}

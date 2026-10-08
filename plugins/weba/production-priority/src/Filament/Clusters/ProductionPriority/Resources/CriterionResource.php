<?php

namespace Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\CriterionResource\Pages\CreateCriterion;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\CriterionResource\Pages\EditCriterion;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\CriterionResource\Pages\ListCriteria;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\CriterionResource\Schemas\CriterionForm;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\CriterionResource\Tables\CriteriaTable;
use Weba\ProductionPriority\Models\Criterion;

class CriterionResource extends Resource
{
    protected static ?string $model = Criterion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?int $navigationSort = 1;

    protected static ?string $cluster = ProductionPriority::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('production-priority::models/criterion.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('production-priority::filament/production-priority.criterion.navigation.title');
    }

    public static function form(Schema $schema): Schema
    {
        return CriterionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CriteriaTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListCriteria::route('/'),
            'create' => CreateCriterion::route('/create'),
            'edit'   => EditCriterion::route('/{record}/edit'),
        ];
    }
}

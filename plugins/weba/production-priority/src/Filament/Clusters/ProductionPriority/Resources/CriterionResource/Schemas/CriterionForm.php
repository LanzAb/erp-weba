<?php

namespace Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\CriterionResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;
use Weba\ProductionPriority\Enums\CriterionSource;
use Weba\ProductionPriority\Enums\CriterionType;

class CriterionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('production-priority::filament/production-priority.criterion.form.section'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label(__('production-priority::filament/production-priority.criterion.form.code'))
                            ->required()
                            ->maxLength(20)
                            ->alphaDash()
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('company_id', current_company_id()),
                            ),

                        TextInput::make('name')
                            ->label(__('production-priority::filament/production-priority.criterion.form.name'))
                            ->required()
                            ->maxLength(255),

                        Select::make('type')
                            ->label(__('production-priority::filament/production-priority.criterion.form.type'))
                            ->options(CriterionType::options())
                            ->required()
                            ->native(false),

                        Select::make('source')
                            ->label(__('production-priority::filament/production-priority.criterion.form.source'))
                            ->options(CriterionSource::options())
                            ->required()
                            ->native(false),

                        TextInput::make('weight')
                            ->label(__('production-priority::filament/production-priority.criterion.form.weight'))
                            ->helperText(__('production-priority::filament/production-priority.criterion.form.weight-hint'))
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(1)
                            ->step(0.01)
                            ->default(0.1),

                        Toggle::make('is_active')
                            ->label(__('production-priority::filament/production-priority.criterion.form.is-active'))
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }
}

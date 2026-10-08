<?php

namespace Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\EvaluationResource\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Weba\ProductionPriority\Enums\CriterionSource;
use Weba\ProductionPriority\Enums\CriterionType;
use Weba\ProductionPriority\Models\Evaluation;

class EvaluationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('production-priority::filament/production-priority.evaluation.infolist.summary'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('production-priority::filament/production-priority.evaluation.table.name'))
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold)
                            ->columnSpanFull(),

                        TextEntry::make('evaluated_at')
                            ->label(__('production-priority::filament/production-priority.evaluation.table.evaluated-at'))
                            ->dateTime(),

                        TextEntry::make('trigger')
                            ->label(__('production-priority::filament/production-priority.evaluation.table.trigger'))
                            ->badge(),

                        TextEntry::make('evaluator.name')
                            ->label(__('production-priority::filament/production-priority.evaluation.table.evaluator'))
                            ->placeholder('—'),

                        TextEntry::make('order_count')
                            ->label(__('production-priority::filament/production-priority.evaluation.table.order-count'))
                            ->numeric(),

                        TextEntry::make('applied_status')
                            ->label(__('production-priority::filament/production-priority.evaluation.infolist.status'))
                            ->state(fn (Evaluation $record): string => $record->isApplied()
                                ? __('production-priority::filament/production-priority.evaluation.infolist.applied', ['count' => $record->applied_top_n])
                                : __('production-priority::filament/production-priority.evaluation.infolist.not-applied'))
                            ->badge()
                            ->color(fn (Evaluation $record): string => $record->isApplied() ? 'danger' : 'gray'),

                        TextEntry::make('notes')
                            ->label(__('production-priority::filament/production-priority.evaluation.infolist.notes'))
                            ->visible(fn (Evaluation $record): bool => filled($record->notes))
                            ->columnSpanFull(),
                    ]),

                Tabs::make('results')
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make(__('production-priority::filament/production-priority.evaluation.infolist.tabs.ranking'))
                            ->icon('heroicon-o-trophy')
                            ->schema([static::rankingTable()]),

                        Tab::make(__('production-priority::filament/production-priority.evaluation.infolist.tabs.decision-matrix'))
                            ->icon('heroicon-o-table-cells')
                            ->schema([static::matrix('raw_values', 2)]),

                        Tab::make(__('production-priority::filament/production-priority.evaluation.infolist.tabs.normalized-matrix'))
                            ->icon('heroicon-o-calculator')
                            ->schema([static::matrix('normalized_values', 4)]),

                        Tab::make(__('production-priority::filament/production-priority.evaluation.infolist.tabs.criteria'))
                            ->icon('heroicon-o-scale')
                            ->schema([static::criteriaTable()]),

                        Tab::make(__('production-priority::filament/production-priority.evaluation.infolist.tabs.warnings'))
                            ->icon('heroicon-o-exclamation-triangle')
                            ->badge(fn (Evaluation $record): int => count($record->warnings ?? []))
                            ->visible(fn (Evaluation $record): bool => ! empty($record->warnings))
                            ->schema([static::warningsTable()]),
                    ]),
            ]);
    }

    protected static function rankingTable(): RepeatableEntry
    {
        return RepeatableEntry::make('items')
            ->hiddenLabel()
            ->contained(false)
            ->table([
                TableColumn::make(__('production-priority::filament/production-priority.evaluation.infolist.ranking.rank')),
                TableColumn::make(__('production-priority::filament/production-priority.evaluation.infolist.ranking.order')),
                TableColumn::make(__('production-priority::filament/production-priority.evaluation.infolist.ranking.product')),
                TableColumn::make(__('production-priority::filament/production-priority.evaluation.infolist.ranking.score')),
                TableColumn::make(__('production-priority::filament/production-priority.evaluation.infolist.ranking.priority')),
            ])
            ->schema([
                TextEntry::make('rank')
                    ->hiddenLabel()
                    ->weight(FontWeight::Bold),

                TextEntry::make('order.name')
                    ->hiddenLabel()
                    ->placeholder('—'),

                TextEntry::make('order.product.name')
                    ->hiddenLabel()
                    ->placeholder('—'),

                TextEntry::make('score')
                    ->hiddenLabel()
                    ->numeric(decimalPlaces: 4),

                TextEntry::make('applied')
                    ->hiddenLabel()
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state
                        ? __('production-priority::filament/production-priority.evaluation.infolist.ranking.urgent')
                        : '—')
                    ->color(fn (mixed $state): string => $state ? 'danger' : 'gray'),
            ]);
    }

    /**
     * One column per criterion of the snapshot, so the matrix still reads
     * correctly after the criteria have been changed.
     */
    protected static function matrix(string $field, int $decimals): RepeatableEntry
    {
        return RepeatableEntry::make('items')
            ->hiddenLabel()
            ->contained(false)
            ->table(fn (Evaluation $record): array => [
                TableColumn::make(__('production-priority::filament/production-priority.evaluation.infolist.ranking.order')),
                ...collect($record->criteria_snapshot)
                    ->map(fn (array $criterion): TableColumn => TableColumn::make($criterion['code'].' · '.$criterion['name']))
                    ->all(),
            ])
            ->schema(fn (Evaluation $record): array => [
                TextEntry::make('order.name')
                    ->hiddenLabel()
                    ->placeholder('—'),
                ...collect($record->criteria_snapshot)
                    ->map(fn (array $criterion): TextEntry => TextEntry::make($field.'.'.$criterion['code'])
                        ->hiddenLabel()
                        ->numeric(decimalPlaces: $decimals)
                        ->placeholder('—'))
                    ->all(),
            ]);
    }

    protected static function criteriaTable(): RepeatableEntry
    {
        return RepeatableEntry::make('criteria_snapshot')
            ->hiddenLabel()
            ->contained(false)
            ->table([
                TableColumn::make(__('production-priority::filament/production-priority.criterion.table.code')),
                TableColumn::make(__('production-priority::filament/production-priority.criterion.table.name')),
                TableColumn::make(__('production-priority::filament/production-priority.criterion.table.type')),
                TableColumn::make(__('production-priority::filament/production-priority.criterion.table.source')),
                TableColumn::make(__('production-priority::filament/production-priority.criterion.table.weight')),
            ])
            ->schema([
                TextEntry::make('code')->hiddenLabel(),

                TextEntry::make('name')->hiddenLabel(),

                TextEntry::make('type')
                    ->hiddenLabel()
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => CriterionType::tryFrom((string) $state)?->getLabel() ?? (string) $state),

                TextEntry::make('source')
                    ->hiddenLabel()
                    ->formatStateUsing(fn (mixed $state): string => CriterionSource::tryFrom((string) $state)?->getLabel() ?? (string) $state),

                TextEntry::make('weight')
                    ->hiddenLabel()
                    ->numeric(decimalPlaces: 4),
            ]);
    }

    protected static function warningsTable(): RepeatableEntry
    {
        return RepeatableEntry::make('warnings')
            ->hiddenLabel()
            ->contained(false)
            ->table([
                TableColumn::make(__('production-priority::filament/production-priority.evaluation.infolist.warnings.order')),
                TableColumn::make(__('production-priority::filament/production-priority.evaluation.infolist.warnings.criterion')),
                TableColumn::make(__('production-priority::filament/production-priority.evaluation.infolist.warnings.reason')),
            ])
            ->schema([
                TextEntry::make('alternative')
                    ->hiddenLabel()
                    ->formatStateUsing(fn (mixed $state): string => '#'.$state),

                TextEntry::make('criterion')->hiddenLabel(),

                TextEntry::make('reason')
                    ->hiddenLabel()
                    ->formatStateUsing(fn (mixed $state): string => __('production-priority::filament/production-priority.evaluation.infolist.warnings.reasons.'.$state)),
            ]);
    }
}

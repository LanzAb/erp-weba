<?php

namespace Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\CriterionResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Weba\ProductionPriority\Filament\Actions\NormalizeWeightsAction;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\CriterionResource;
use Weba\ProductionPriority\Models\Criterion;
use Weba\ProductionPriority\Services\SawCalculator;

class ListCriteria extends ListRecords
{
    protected static string $resource = CriterionResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        $total = round((float) Criterion::query()->active()->sum('weight'), 4);

        $isValid = abs($total - 1.0) <= SawCalculator::WEIGHT_TOLERANCE;

        return __(
            $isValid
                ? 'production-priority::filament/production-priority.criterion.total.valid'
                : 'production-priority::filament/production-priority.criterion.total.invalid',
            ['total' => number_format($total, 4)],
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
            NormalizeWeightsAction::make(),
        ];
    }
}

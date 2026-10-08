<?php

namespace Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\EvaluationResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Weba\ProductionPriority\Filament\Actions\RunEvaluationAction;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\EvaluationResource;

class ListEvaluations extends ListRecords
{
    protected static string $resource = EvaluationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RunEvaluationAction::make(),
        ];
    }
}

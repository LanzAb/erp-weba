<?php

namespace Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\OrderMonitoringResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Weba\ProductionPriority\Filament\Actions\RunEvaluationAction;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\OrderMonitoringResource;

class ListOrderMonitoring extends ListRecords
{
    protected static string $resource = OrderMonitoringResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RunEvaluationAction::make(),
        ];
    }
}

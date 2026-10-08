<?php

namespace Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\CriterionResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\CriterionResource;

class EditCriterion extends EditRecord
{
    protected static string $resource = CriterionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

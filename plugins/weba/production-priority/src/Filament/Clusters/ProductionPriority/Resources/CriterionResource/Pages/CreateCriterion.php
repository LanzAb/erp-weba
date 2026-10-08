<?php

namespace Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\CriterionResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\CriterionResource;

class CreateCriterion extends CreateRecord
{
    protected static string $resource = CriterionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] ??= current_company_id();

        $data['sort'] ??= (int) CriterionResource::getModel()::query()->max('sort') + 1;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

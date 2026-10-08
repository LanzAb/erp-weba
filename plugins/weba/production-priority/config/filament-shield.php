<?php

use Weba\ProductionPriority\Filament\Clusters\ProductionPriority;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\CriterionResource;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\EvaluationResource;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\OrderMonitoringResource;

$basic = ['view_any', 'view', 'create', 'update'];
$delete = ['delete', 'delete_any'];
$reorder = ['reorder'];

return [
    'resources' => [
        'manage' => [
            CriterionResource::class  => [...$basic, ...$delete, ...$reorder],
            EvaluationResource::class => [...$basic],
        ],
        'exclude' => [
            OrderMonitoringResource::class,
        ],
    ],

    'pages' => [
        'exclude' => [
            ProductionPriority::class,
        ],
    ],
];

<?php

namespace Weba\ProductionPriority\Filament\Clusters;

use Filament\Clusters\Cluster;
use Webkul\Support\Enums\NavigationGroup;

class ProductionPriority extends Cluster
{
    protected static ?string $slug = 'manufacturing/production-priority';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('production-priority::filament/production-priority.cluster.navigation.title');
    }

    public static function getNavigationGroup(): string|\UnitEnum
    {
        return NavigationGroup::Manufacturing;
    }
}

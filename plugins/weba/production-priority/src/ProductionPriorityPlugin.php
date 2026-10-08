<?php

namespace Weba\ProductionPriority;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Webkul\PluginManager\Package;

class ProductionPriorityPlugin implements Plugin
{
    public function getId(): string
    {
        return 'production-priority';
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function register(Panel $panel): void
    {
        if (! Package::isPluginInstalled($this->getId())) {
            return;
        }

        $panel
            ->when($panel->getId() == 'admin', function (Panel $panel): void {
                $panel
                    ->discoverPages(
                        in: __DIR__.'/Filament/Pages',
                        for: 'Weba\\ProductionPriority\\Filament\\Pages'
                    )
                    ->discoverClusters(
                        in: __DIR__.'/Filament/Clusters',
                        for: 'Weba\\ProductionPriority\\Filament\\Clusters'
                    )
                    ->discoverWidgets(
                        in: __DIR__.'/Filament/Widgets',
                        for: 'Weba\\ProductionPriority\\Filament\\Widgets'
                    );
            });
    }

    public function boot(Panel $panel): void
    {
        //
    }
}

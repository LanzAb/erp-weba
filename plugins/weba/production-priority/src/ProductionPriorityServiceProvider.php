<?php

namespace Weba\ProductionPriority;

use Filament\Panel;
use Illuminate\Console\Scheduling\Schedule;
use Weba\ProductionPriority\Console\RunEvaluationCommand;
use Weba\ProductionPriority\Models\OrderAttribute;
use Weba\ProductionPriority\Services\ProductionOverview;
use Webkul\Manufacturing\Models\Order;
use Webkul\PluginManager\Console\Commands\InstallCommand;
use Webkul\PluginManager\Console\Commands\UninstallCommand;
use Webkul\PluginManager\Package;
use Webkul\PluginManager\PackageServiceProvider;

class ProductionPriorityServiceProvider extends PackageServiceProvider
{
    public static string $name = 'production-priority';

    public static string $viewNamespace = 'production-priority';

    public function configureCustomPackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasViews()
            ->hasTranslations()
            ->hasMigrations([
                '2026_10_08_000001_create_production_priority_criteria_table',
                '2026_10_08_000002_create_production_priority_order_attributes_table',
                '2026_10_08_000003_create_production_priority_evaluations_table',
                '2026_10_08_000004_create_production_priority_evaluation_items_table',
            ])
            ->runsMigrations()
            ->hasDependencies([
                'manufacturing',
            ])
            ->hasCommand(RunEvaluationCommand::class)
            ->hasSeeder('Weba\\ProductionPriority\\Database\\Seeders\\DatabaseSeeder')
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->installDependencies()
                    ->runsMigrations()
                    ->runsSeeders();
            })
            ->hasUninstallCommand(function (UninstallCommand $command): void {})
            ->icon('manufacturing');
    }

    public function packageBooted(): void
    {
        $this->contributeOrderRelations();

        $this->scheduleDailyEvaluation();
    }

    protected function scheduleDailyEvaluation(): void
    {
        if (! Package::isPluginInstalled(static::$name)) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('production-priority:run')
                ->dailyAt('06:00')
                ->withoutOverlapping();
        });
    }

    protected function contributeOrderRelations(): void
    {
        if (! Package::isPluginInstalled(static::$name)) {
            return;
        }

        Order::resolveRelationUsing('priorityAttribute', fn (Order $order) => $order->hasOne(
            OrderAttribute::class,
            'manufacturing_order_id',
        ));
    }

    public function packageRegistered(): void
    {
        $this->app->scoped(ProductionOverview::class);

        Panel::configureUsing(function (Panel $panel): void {
            $panel->plugin(ProductionPriorityPlugin::make());
        });
    }
}

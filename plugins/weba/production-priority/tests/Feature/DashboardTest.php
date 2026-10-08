<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Weba\ProductionPriority\Database\Seeders\CriterionSeeder;
use Weba\ProductionPriority\Filament\Pages\ProductionDashboard;
use Weba\ProductionPriority\Filament\Widgets\LateOrdersWidget;
use Weba\ProductionPriority\Filament\Widgets\OrdersByStatusChart;
use Weba\ProductionPriority\Filament\Widgets\PriorityRankingWidget;
use Weba\ProductionPriority\Filament\Widgets\ProductionStatsWidget;
use Weba\ProductionPriority\Filament\Widgets\WorkCenterLoadWidget;
use Weba\ProductionPriority\Services\EvaluationService;
use Weba\ProductionPriority\Services\ProductionOverview;
use Webkul\Manufacturing\Enums\WorkOrderState;
use Webkul\Manufacturing\Models\Order;
use Webkul\PluginManager\Models\Plugin;
use Webkul\PluginManager\Package;

require_once __DIR__.'/../../../../webkul/support/tests/Helpers/TestBootstrapHelper.php';
require_once __DIR__.'/../../../../webkul/inventories/tests/Helpers/InventoryHelper.php';
require_once __DIR__.'/../../../../webkul/manufacturing/tests/Helpers/ManufacturingHelper.php';

beforeEach(function () {
    TestBootstrapHelper::ensurePluginInstalled('inventories');
    TestBootstrapHelper::ensurePluginInstalled('manufacturing');
    TestBootstrapHelper::ensurePluginInstalled('production-priority');

    (new CriterionSeeder)->run();

    foreach (['inventories', 'manufacturing', 'production-priority'] as $plugin) {
        DB::table('plugins')->updateOrInsert(
            ['name' => $plugin],
            ['is_installed' => true, 'is_active' => true, 'updated_at' => now()],
        );
    }

    Package::$plugins = Plugin::all()->keyBy('name');

    URL::resolveMissingNamedRoutesUsing(fn () => '#');

    ManufacturingHelper::actingAsAdmin();

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->company = ManufacturingHelper::company();
    $this->warehouse = InventoryHelper::warehouse();
    $this->finished = ManufacturingHelper::product();
    $this->component = ManufacturingHelper::product();
    $this->workCenter = ManufacturingHelper::workCenter();
});

function dashOrder(object $test, ?int $deadlineInDays = 5, bool $confirmed = true): Order
{
    $bom = ManufacturingHelper::bom($test->finished, [[$test->component, 2]]);
    $order = ManufacturingHelper::order($test->warehouse, $test->finished, $bom, 5);

    if ($confirmed) {
        $order = ManufacturingHelper::confirm($order);
    }

    DB::table('manufacturing_orders')->where('id', $order->id)->update([
        'deadline_at' => $deadlineInDays === null ? null : now()->addDays($deadlineInDays),
    ]);

    return $order->refresh();
}

function dashChartData(Testable $component): array
{
    return (fn () => $this->getData())->call($component->instance());
}

describe('production overview', function () {
    it('summarizes the open orders', function () {
        dashOrder($this, -3);
        dashOrder($this, 1);
        dashOrder($this, 20);
        dashOrder($this, 5, confirmed: false);

        $stats = app(ProductionOverview::class)->stats();

        expect($stats['total'])->toBe(3)
            ->and($stats['late'])->toBe(1)
            ->and($stats['due_soon'])->toBe(1)
            ->and($stats['material_short'])->toBe(3);
    });

    it('loads the orders only once', function () {
        dashOrder($this, 5);

        $overview = app(ProductionOverview::class);

        expect($overview->orders())->toBe($overview->orders());
    });
});

describe('dashboard page', function () {
    it('renders', function () {
        Livewire::test(ProductionDashboard::class)->assertSuccessful();
    });

    it('lists the five widgets', function () {
        $widgets = (new ProductionDashboard)->getWidgets();

        expect($widgets)->toBe([
            ProductionStatsWidget::class,
            OrdersByStatusChart::class,
            WorkCenterLoadWidget::class,
            PriorityRankingWidget::class,
            LateOrdersWidget::class,
        ]);
    });
});

describe('stats widget', function () {
    it('shows the figures of the open orders', function () {
        dashOrder($this, -3);
        dashOrder($this, 1);

        Livewire::test(ProductionStatsWidget::class)
            ->assertSuccessful()
            ->assertSee('Open orders')
            ->assertSee('Late')
            ->assertSee('Due soon')
            ->assertSee('Average progress');
    });

    it('renders without any order', function () {
        Livewire::test(ProductionStatsWidget::class)->assertSuccessful();
    });
});

describe('status chart', function () {
    it('counts the orders per status and leaves out old finished orders', function () {
        $draft = dashOrder($this, 5, confirmed: false);
        dashOrder($this, 5);
        dashOrder($this, 5);

        $recent = dashOrder($this, 5);
        $old = dashOrder($this, 5);

        DB::table('manufacturing_orders')->where('id', $recent->id)->update(['state' => 'done', 'finished_at' => now()->subDays(2)]);
        DB::table('manufacturing_orders')->where('id', $old->id)->update(['state' => 'done', 'finished_at' => now()->subDays(60)]);

        $data = dashChartData(Livewire::test(OrdersByStatusChart::class));

        $byLabel = array_combine($data['labels'], $data['datasets'][0]['data']);

        expect($byLabel['Draft'])->toBe(1)
            ->and($byLabel['Confirmed'])->toBe(2)
            ->and($byLabel['Done'])->toBe(1)
            ->and($draft->state->value)->toBe('draft');
    });

    it('renders', function () {
        Livewire::test(OrdersByStatusChart::class)->assertSuccessful();
    });
});

describe('work center load', function () {
    it('sums the work left per work center in hours', function () {
        $order = dashOrder($this, 5);

        $running = ManufacturingHelper::workOrder($order, $this->workCenter, null, [
            'state' => WorkOrderState::PROGRESS, 'expected_duration' => 120,
        ]);
        ManufacturingHelper::logProductivity($running, 60, 'productive');
        $running->refresh()->load('productivityLogs');
        $running->computeDuration();
        $running->saveQuietly();

        ManufacturingHelper::workOrder($order, $this->workCenter, null, [
            'state' => WorkOrderState::PENDING, 'expected_duration' => 30,
        ]);
        ManufacturingHelper::workOrder($order, $this->workCenter, null, [
            'state' => WorkOrderState::DONE, 'expected_duration' => 999,
        ]);

        $data = dashChartData(Livewire::test(WorkCenterLoadWidget::class));

        expect($data['labels'])->toBe([$this->workCenter->name])
            ->and($data['datasets'][0]['data'])->toBe([1.5]);
    });

    it('ignores work of orders that are not open', function () {
        $draft = dashOrder($this, 5, confirmed: false);

        ManufacturingHelper::workOrder($draft, $this->workCenter, null, [
            'state' => WorkOrderState::PENDING, 'expected_duration' => 60,
        ]);

        expect(dashChartData(Livewire::test(WorkCenterLoadWidget::class))['labels'])->toBe([]);
    });
});

describe('table widgets', function () {
    it('build their table without querying the database', function (string $widgetClass) {
        DB::enableQueryLog();

        $widget = new $widgetClass;

        (fn () => $this->makeTable())->call($widget);

        $pluginQueries = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $sql): bool => str_contains($sql, 'production_priority') || str_contains($sql, 'manufacturing_orders'));

        DB::disableQueryLog();

        expect($pluginQueries->all())->toBe([]);
    })->with([
        'priority ranking' => PriorityRankingWidget::class,
        'late orders'      => LateOrdersWidget::class,
    ]);
});

describe('priority ranking widget', function () {
    it('shows the empty state before any evaluation', function () {
        Livewire::test(PriorityRankingWidget::class)
            ->assertSuccessful()
            ->assertSee('No SAW evaluation yet');
    });

    it('lists the orders of the latest evaluation by rank', function () {
        dashOrder($this, 1);
        dashOrder($this, 30);

        $evaluation = app(EvaluationService::class)->run($this->company->id, name: 'Evaluasi dashboard');

        Livewire::test(PriorityRankingWidget::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($evaluation->items, inOrder: true)
            ->assertSee('Evaluasi dashboard');
    });

    it('limits the list to the top ten', function () {
        foreach (range(1, 12) as $day) {
            dashOrder($this, $day);
        }

        $evaluation = app(EvaluationService::class)->run($this->company->id);

        Livewire::test(PriorityRankingWidget::class)
            ->assertCanSeeTableRecords($evaluation->items->take(PriorityRankingWidget::LIMIT), inOrder: true)
            ->assertCanNotSeeTableRecords($evaluation->items->slice(PriorityRankingWidget::LIMIT));
    });
});

describe('late orders widget', function () {
    it('lists only the open orders past their deadline, most overdue first', function () {
        $veryLate = dashOrder($this, -10);
        $late = dashOrder($this, -1);
        $upcoming = dashOrder($this, 4);
        $noDeadline = dashOrder($this, null);
        $draft = dashOrder($this, -5, confirmed: false);

        Livewire::test(LateOrdersWidget::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$veryLate, $late], inOrder: true)
            ->assertCanNotSeeTableRecords([$upcoming, $noDeadline, $draft]);
    });

    it('shows a friendly message when nothing is late', function () {
        dashOrder($this, 5);

        Livewire::test(LateOrdersWidget::class)
            ->assertSuccessful()
            ->assertSee('No late orders');
    });
});

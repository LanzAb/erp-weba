<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Weba\ProductionPriority\Database\Seeders\CriterionSeeder;
use Weba\ProductionPriority\Models\Criterion;
use Weba\ProductionPriority\Models\OrderAttribute;
use Weba\ProductionPriority\Services\CriterionValueResolver;
use Weba\ProductionPriority\Services\OrderProgressService;
use Weba\ProductionPriority\Services\SawCalculator;
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

    $this->warehouse = InventoryHelper::warehouse();
    $this->stock = $this->warehouse->lotStockLocation;
    $this->finished = ManufacturingHelper::product();
    $this->component = ManufacturingHelper::product();
    $this->workCenter = ManufacturingHelper::workCenter();
});

function newOrder(object $test, float $quantity = 5): Order
{
    $bom = ManufacturingHelper::bom($test->finished, [[$test->component, 2]]);

    return ManufacturingHelper::order($test->warehouse, $test->finished, $bom, $quantity);
}

function runningWorkOrder(object $test, Order $order, float $expected, float $producedMinutes): void
{
    $workOrder = ManufacturingHelper::workOrder($order, $test->workCenter, null, [
        'state'             => WorkOrderState::PROGRESS,
        'expected_duration' => $expected,
    ]);

    ManufacturingHelper::logProductivity($workOrder, $producedMinutes, 'productive');

    $workOrder->refresh()->load('productivityLogs');
    $workOrder->computeDuration();
    $workOrder->saveQuietly();
}

function setDeadline(Order $order, ?string $deadline): Order
{
    DB::table('manufacturing_orders')->where('id', $order->id)->update(['deadline_at' => $deadline]);

    return $order->refresh();
}

describe('order attributes', function () {
    it('is reachable through the order relation', function () {
        $order = newOrder($this);

        OrderAttribute::factory()->create([
            'manufacturing_order_id' => $order->id,
            'urgency_level'          => 5,
            'order_value'            => 125000000,
        ]);

        $attribute = $order->fresh()->priorityAttribute;

        expect($attribute)->toBeInstanceOf(OrderAttribute::class)
            ->and($attribute->urgency_level)->toBe(5)
            ->and($attribute->order_value)->toBe('125000000.00');
    });

    it('defaults the urgency level to 3', function () {
        $order = newOrder($this);

        $attribute = OrderAttribute::query()->create(['manufacturing_order_id' => $order->id])->fresh();

        expect($attribute->urgency_level)->toBe(OrderAttribute::DEFAULT_URGENCY_LEVEL);
    });

    it('allows only one attribute row per order', function () {
        $order = newOrder($this);

        OrderAttribute::factory()->create(['manufacturing_order_id' => $order->id]);

        OrderAttribute::factory()->create(['manufacturing_order_id' => $order->id]);
    })->throws(QueryException::class);

    it('rejects an urgency level outside 1 to 5', function (int $level) {
        $order = newOrder($this);

        OrderAttribute::factory()->urgency($level)->create(['manufacturing_order_id' => $order->id]);
    })->with([0, 6])->throws(InvalidArgumentException::class, 'between 1 and 5');

    it('rejects a negative order value', function () {
        $order = newOrder($this);

        OrderAttribute::factory()->orderValue(-1)->create(['manufacturing_order_id' => $order->id]);
    })->throws(InvalidArgumentException::class, 'must not be negative');
});

describe('order progress service', function () {
    it('weights work orders by expected duration', function () {
        $order = newOrder($this);

        ManufacturingHelper::workOrder($order, $this->workCenter, null, [
            'state' => WorkOrderState::DONE, 'expected_duration' => 60, 'duration' => 70,
        ]);
        runningWorkOrder($this, $order, 120, 30);

        $progress = app(OrderProgressService::class)->forOrder($order->refresh());

        expect($progress->progressPercent)->toBe(50.0)
            ->and($progress->remainingMinutes)->toBe(90.0)
            ->and($progress->remainingHours())->toBe(1.5);
    });

    it('flags an open order past its deadline as late', function () {
        $order = setDeadline(newOrder($this), now()->subDays(2)->toDateTimeString());

        $progress = app(OrderProgressService::class)->forOrder($order);

        expect($progress->isLate)->toBeTrue()
            ->and($progress->daysToDeadline)->toBeLessThan(0);
    });

    it('does not flag an order whose deadline is still ahead', function () {
        $order = setDeadline(newOrder($this), now()->addDays(5)->toDateTimeString());

        $progress = app(OrderProgressService::class)->forOrder($order);

        expect($progress->isLate)->toBeFalse()
            ->and($progress->daysToDeadline)->toBeGreaterThan(4.9);
    });

    it('reports material availability from the reserved quantities', function () {
        $stocked = ManufacturingHelper::product();
        $missing = ManufacturingHelper::product();

        InventoryHelper::stockUp($stocked, $this->stock, 10);

        $fullOrder = ManufacturingHelper::confirm(
            ManufacturingHelper::order($this->warehouse, $this->finished, ManufacturingHelper::bom($this->finished, [[$stocked, 2]]), 5)
        );
        $emptyOrder = ManufacturingHelper::confirm(
            ManufacturingHelper::order($this->warehouse, $this->finished, ManufacturingHelper::bom($this->finished, [[$missing, 2]]), 5)
        );

        $service = app(OrderProgressService::class);

        expect($service->forOrder($fullOrder->refresh())->materialAvailabilityPercent)->toBe(100.0)
            ->and($service->forOrder($emptyOrder->refresh())->materialAvailabilityPercent)->toBe(0.0);
    });

    it('runs the same number of queries for one order and for many', function () {
        $orders = collect(range(1, 5))->map(fn () => newOrder($this));
        $service = app(OrderProgressService::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $service->forOrders(Order::query()->whereKey($orders->first()->id)->get());
        $single = count(DB::getQueryLog());

        DB::flushQueryLog();
        $service->forOrders(Order::query()->whereKey($orders->pluck('id'))->get());
        $many = count(DB::getQueryLog());

        DB::disableQueryLog();

        expect($many)->toBe($single);
    });
});

describe('criterion value resolver', function () {
    beforeEach(function () {
        $this->criteria = Criterion::withoutGlobalScopes()
            ->where('company_id', ManufacturingHelper::company()->id)
            ->active()
            ->orderBy('sort')
            ->get();
    });

    it('reads every criterion from the production data', function () {
        $order = setDeadline(newOrder($this), now()->addDays(10)->toDateTimeString());

        OrderAttribute::factory()->urgency(5)->orderValue(250000000)->create(['manufacturing_order_id' => $order->id]);
        runningWorkOrder($this, $order, 120, 60);

        $values = app(CriterionValueResolver::class)->resolve([$order->refresh()], $this->criteria)[$order->id];

        expect($values['C1'])->toBeGreaterThan(9.9)->toBeLessThanOrEqual(10.0)
            ->and($values['C2'])->toBe(5.0)
            ->and($values['C3'])->toBe(0.0)
            ->and($values['C4'])->toBe(1.0)
            ->and($values['C5'])->toBe(50.0)
            ->and($values['C6'])->toBe(250000000.0);
    });

    it('uses the default urgency, no order value and zero days for an overdue order without attributes', function () {
        $order = setDeadline(newOrder($this), now()->subDays(3)->toDateTimeString());

        $values = app(CriterionValueResolver::class)->resolve([$order], $this->criteria)[$order->id];

        expect($values['C1'])->toBe(0.0)
            ->and($values['C2'])->toBe((float) OrderAttribute::DEFAULT_URGENCY_LEVEL)
            ->and($values['C6'])->toBeNull();
    });

    it('leaves the days criterion unknown when the order has no deadline', function () {
        $order = setDeadline(newOrder($this), null);

        $values = app(CriterionValueResolver::class)->resolve([$order], $this->criteria)[$order->id];

        expect($values['C1'])->toBeNull();
    });

    it('ranks the urgent, nearly due order first when fed to the SAW calculator', function () {
        $urgent = setDeadline(newOrder($this), now()->addDay()->toDateTimeString());
        $relaxed = setDeadline(newOrder($this), now()->addDays(30)->toDateTimeString());

        OrderAttribute::factory()->urgency(5)->orderValue(300000000)->create(['manufacturing_order_id' => $urgent->id]);
        OrderAttribute::factory()->urgency(1)->orderValue(20000000)->create(['manufacturing_order_id' => $relaxed->id]);

        $resolver = app(CriterionValueResolver::class);
        $orders = collect([$relaxed->refresh(), $urgent->refresh()]);

        $result = (new SawCalculator)->calculate(
            $resolver->resolve($orders, $this->criteria),
            $this->criteria->map(fn (Criterion $criterion) => $criterion->toSawCriterion())->all(),
            $resolver->tieBreakers($orders),
        );

        expect($result->ranking)->toBe([$urgent->id, $relaxed->id]);
    });

    it('uses the deadline as tie breaker', function () {
        $first = setDeadline(newOrder($this), now()->addDays(2)->toDateTimeString());
        $second = setDeadline(newOrder($this), now()->addDays(9)->toDateTimeString());

        $tieBreakers = app(CriterionValueResolver::class)->tieBreakers([$first->refresh(), $second->refresh()]);

        expect($tieBreakers[$first->id])->toBeLessThan($tieBreakers[$second->id]);
    });
});

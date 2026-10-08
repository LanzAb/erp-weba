<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Weba\ProductionPriority\Database\Seeders\CriterionSeeder;
use Weba\ProductionPriority\Enums\EvaluationTrigger;
use Weba\ProductionPriority\Models\Criterion;
use Weba\ProductionPriority\Models\Evaluation;
use Weba\ProductionPriority\Models\OrderAttribute;
use Weba\ProductionPriority\Services\EvaluationService;
use Weba\ProductionPriority\Services\PriorityApplier;
use Webkul\Manufacturing\Enums\ManufacturingOrderPriority;
use Webkul\Manufacturing\Models\Order;
use Webkul\PluginManager\Models\Plugin;
use Webkul\PluginManager\Package;
use Webkul\Support\Models\Company;

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

    $this->admin = ManufacturingHelper::actingAsAdmin();

    $this->company = ManufacturingHelper::company();
    $this->warehouse = InventoryHelper::warehouse();
    $this->finished = ManufacturingHelper::product();
    $this->component = ManufacturingHelper::product();
    $this->service = app(EvaluationService::class);
});

function evalOrder(object $test, ?int $deadlineInDays = null, int $urgency = 3, ?float $value = null, bool $confirmed = true): Order
{
    $bom = ManufacturingHelper::bom($test->finished, [[$test->component, 2]]);
    $order = ManufacturingHelper::order($test->warehouse, $test->finished, $bom, 5);

    if ($confirmed) {
        $order = ManufacturingHelper::confirm($order);
    }

    if ($deadlineInDays !== null) {
        DB::table('manufacturing_orders')->where('id', $order->id)->update(['deadline_at' => now()->addDays($deadlineInDays)]);
    }

    OrderAttribute::factory()->urgency($urgency)->orderValue($value)->create(['manufacturing_order_id' => $order->id]);

    return $order->refresh();
}

function evalPriority(Order $order): ManufacturingOrderPriority
{
    return Order::withoutGlobalScopes()->findOrFail($order->id)->priority;
}

describe('running an evaluation', function () {
    it('ranks the open orders and stores a snapshot of the criteria', function () {
        $urgent = evalOrder($this, 1, 5, 300000000);
        $relaxed = evalOrder($this, 30, 1, 20000000);

        $evaluation = $this->service->run($this->company->id, name: 'Uji SAW', notes: 'catatan');

        expect($evaluation->name)->toBe('Uji SAW')
            ->and($evaluation->trigger)->toBe(EvaluationTrigger::MANUAL)
            ->and($evaluation->evaluated_by)->toBe($this->admin->id)
            ->and($evaluation->order_count)->toBe(2)
            ->and(collect($evaluation->criteria_snapshot)->pluck('code')->all())->toBe(['C1', 'C2', 'C3', 'C4', 'C5', 'C6'])
            ->and(round(collect($evaluation->criteria_snapshot)->sum('weight'), 4))->toBe(1.0);

        $items = $evaluation->items;

        expect($items->pluck('manufacturing_order_id')->all())->toBe([$urgent->id, $relaxed->id])
            ->and($items->pluck('rank')->all())->toBe([1, 2])
            ->and((float) $items[0]->score)->toBeGreaterThan((float) $items[1]->score)
            ->and(array_keys($items[0]->raw_values))->toBe(['C1', 'C2', 'C3', 'C4', 'C5', 'C6'])
            ->and(array_keys($items[0]->normalized_values))->toBe(['C1', 'C2', 'C3', 'C4', 'C5', 'C6']);
    });

    it('leaves out draft orders', function () {
        evalOrder($this, 5);
        evalOrder($this, 5, confirmed: false);

        expect($this->service->run($this->company->id)->order_count)->toBe(1);
    });

    it('returns null when there is no open order', function () {
        evalOrder($this, 5, confirmed: false);

        expect($this->service->run($this->company->id))->toBeNull();
    });

    it('marks a scheduled run without an evaluator', function () {
        evalOrder($this, 5);

        $evaluation = $this->service->run($this->company->id, EvaluationTrigger::SCHEDULED);

        expect($evaluation->trigger)->toBe(EvaluationTrigger::SCHEDULED)
            ->and($evaluation->evaluated_by)->toBeNull();
    });

    it('records a warning for a criterion without a value', function () {
        evalOrder($this, 5, value: null);

        $evaluation = $this->service->run($this->company->id);

        expect(collect($evaluation->warnings)->pluck('criterion')->all())->toContain('C6');
    });

    it('does not include orders of another company', function () {
        $own = evalOrder($this, 5);
        $foreign = evalOrder($this, 5);

        DB::table('manufacturing_orders')->where('id', $foreign->id)->update(['company_id' => Company::factory()->create()->id]);

        $evaluation = $this->service->run($this->company->id);

        expect($evaluation->items->pluck('manufacturing_order_id')->all())->toBe([$own->id]);
    });

    it('refuses to run when the active weights do not add up to one', function () {
        evalOrder($this, 5);

        Criterion::withoutGlobalScopes()->where('company_id', $this->company->id)->where('code', 'C6')->update(['weight' => 0.5]);

        $before = Evaluation::withoutGlobalScopes()->count();

        expect(fn () => $this->service->run($this->company->id))
            ->toThrow(InvalidArgumentException::class, 'must be 1');

        expect(Evaluation::withoutGlobalScopes()->count())->toBe($before);
    });

    it('refuses to run without any active criterion', function () {
        evalOrder($this, 5);

        Criterion::withoutGlobalScopes()->where('company_id', $this->company->id)->update(['is_active' => false]);

        $this->service->run($this->company->id);
    })->throws(InvalidArgumentException::class, 'At least one active criterion');
});

describe('evaluation snapshot', function () {
    it('keeps the original weights when the criteria change later', function () {
        evalOrder($this, 5);

        $evaluation = $this->service->run($this->company->id);

        Criterion::withoutGlobalScopes()->where('company_id', $this->company->id)->where('code', 'C1')->update(['weight' => 0.20]);
        Criterion::withoutGlobalScopes()->where('company_id', $this->company->id)->where('code', 'C2')->update(['weight' => 0.30]);

        $snapshot = collect(Evaluation::withoutGlobalScopes()->find($evaluation->id)->criteria_snapshot)->keyBy('code');

        expect($snapshot['C1']['weight'])->toBe(0.3)
            ->and($snapshot['C2']['weight'])->toBe(0.2);
    });

    it('rejects changes to the stored result', function () {
        evalOrder($this, 5);

        $evaluation = $this->service->run($this->company->id);

        $evaluation->items->first()->update(['score' => 0.5]);
    })->throws(LogicException::class, 'can not be changed');

    it('rejects changes to the stored criteria', function () {
        evalOrder($this, 5);

        $evaluation = $this->service->run($this->company->id);

        $evaluation->update(['criteria_snapshot' => []]);
    })->throws(LogicException::class, 'can not be changed');

    it('still allows adding notes', function () {
        evalOrder($this, 5);

        $evaluation = $this->service->run($this->company->id);
        $evaluation->update(['notes' => 'ditinjau PPIC']);

        expect($evaluation->fresh()->notes)->toBe('ditinjau PPIC');
    });
});

describe('applying the priority', function () {
    beforeEach(function () {
        $this->urgent = evalOrder($this, 1, 5, 300000000);
        $this->relaxed = evalOrder($this, 30, 1, 20000000);
        $this->evaluation = $this->service->run($this->company->id, name: 'Evaluasi Uji');
        $this->applier = app(PriorityApplier::class);
    });

    it('marks the top ranked orders as urgent and leaves the rest alone', function () {
        $changed = $this->applier->apply($this->evaluation, 1, 'Pelanggan prioritas');

        $item = $this->evaluation->items()->where('manufacturing_order_id', $this->urgent->id)->first();

        expect($changed)->toBe(1)
            ->and(evalPriority($this->urgent))->toBe(ManufacturingOrderPriority::URGENT)
            ->and(evalPriority($this->relaxed))->toBe(ManufacturingOrderPriority::NORMAL)
            ->and($item->applied)->toBeTrue()
            ->and($item->previous_priority)->toBe('0')
            ->and($this->evaluation->fresh()->isApplied())->toBeTrue()
            ->and($this->evaluation->fresh()->applied_top_n)->toBe(1)
            ->and($this->evaluation->fresh()->applied_by)->toBe($this->admin->id);
    });

    it('leaves a note on the order with the evaluation and the reason', function () {
        $this->applier->apply($this->evaluation, 1, 'Pelanggan prioritas');

        $bodies = Order::withoutGlobalScopes()->find($this->urgent->id)->messages()->get()->pluck('body');

        expect($bodies->contains(fn ($body) => str_contains((string) $body, 'Evaluasi Uji') && str_contains((string) $body, 'Pelanggan prioritas')))->toBeTrue();
    });

    it('can not be applied twice', function () {
        $this->applier->apply($this->evaluation, 1);

        $this->applier->apply($this->evaluation->fresh(), 2);
    })->throws(LogicException::class, 'already applied');

    it('needs at least one order', function () {
        $this->applier->apply($this->evaluation, 0);
    })->throws(InvalidArgumentException::class, 'At least one order');

    it('skips orders that are already urgent and does not revert them later', function () {
        DB::table('manufacturing_orders')->where('id', $this->relaxed->id)->update(['priority' => ManufacturingOrderPriority::URGENT->value]);

        $changed = $this->applier->apply($this->evaluation, 2);

        $this->applier->revert($this->evaluation->fresh());

        expect($changed)->toBe(1)
            ->and(evalPriority($this->urgent))->toBe(ManufacturingOrderPriority::NORMAL)
            ->and(evalPriority($this->relaxed))->toBe(ManufacturingOrderPriority::URGENT);
    });

    it('skips orders that are no longer open', function () {
        DB::table('manufacturing_orders')->where('id', $this->urgent->id)->update(['state' => 'done']);

        $changed = $this->applier->apply($this->evaluation, 1);

        expect($changed)->toBe(0)
            ->and(evalPriority($this->urgent))->toBe(ManufacturingOrderPriority::NORMAL);
    });

    it('restores the previous priority when reverted', function () {
        $this->applier->apply($this->evaluation, 1);

        $restored = $this->applier->revert($this->evaluation->fresh());

        expect($restored)->toBe(1)
            ->and(evalPriority($this->urgent))->toBe(ManufacturingOrderPriority::NORMAL)
            ->and($this->evaluation->fresh()->isApplied())->toBeFalse()
            ->and($this->evaluation->items()->where('applied', true)->count())->toBe(0);
    });

    it('does not overwrite a priority somebody changed after applying', function () {
        $this->applier->apply($this->evaluation, 1);

        DB::table('manufacturing_orders')->where('id', $this->urgent->id)->update(['priority' => ManufacturingOrderPriority::NORMAL->value]);

        $restored = $this->applier->revert($this->evaluation->fresh());

        expect($restored)->toBe(0)
            ->and(evalPriority($this->urgent))->toBe(ManufacturingOrderPriority::NORMAL);
    });

    it('can not revert an evaluation that was not applied', function () {
        $this->applier->revert($this->evaluation);
    })->throws(LogicException::class, 'not applied');

    it('can be applied again after reverting', function () {
        $this->applier->apply($this->evaluation, 1);
        $this->applier->revert($this->evaluation->fresh());

        expect($this->applier->apply($this->evaluation->fresh(), 2))->toBe(2);
    });
});

describe('scheduled run', function () {
    it('evaluates every company through the console command', function () {
        evalOrder($this, 5);

        $this->artisan('production-priority:run', ['--company' => $this->company->id])
            ->expectsOutputToContain('saved (1 orders)')
            ->assertExitCode(0);

        $evaluation = Evaluation::withoutGlobalScopes()->where('company_id', $this->company->id)->latest('id')->first();

        expect($evaluation->trigger)->toBe(EvaluationTrigger::SCHEDULED);
    });

    it('reports when there is nothing to rank', function () {
        $this->artisan('production-priority:run', ['--company' => $this->company->id])
            ->expectsOutputToContain('no open manufacturing order')
            ->assertExitCode(0);
    });

    it('fails when the weights are invalid', function () {
        evalOrder($this, 5);

        Criterion::withoutGlobalScopes()->where('company_id', $this->company->id)->where('code', 'C6')->update(['weight' => 0.5]);

        $this->artisan('production-priority:run', ['--company' => $this->company->id])->assertExitCode(1);
    });

    it('is scheduled every day at 06:00', function () {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'production-priority:run'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('0 6 * * *');
    });
});

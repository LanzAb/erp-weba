<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Weba\ProductionPriority\Database\Seeders\CriterionSeeder;
use Weba\ProductionPriority\Enums\CriterionSource;
use Weba\ProductionPriority\Enums\CriterionType;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\CriterionResource\Pages\CreateCriterion;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\CriterionResource\Pages\EditCriterion;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\CriterionResource\Pages\ListCriteria;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\EvaluationResource\Pages\ListEvaluations;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\EvaluationResource\Pages\ViewEvaluation;
use Weba\ProductionPriority\Filament\Clusters\ProductionPriority\Resources\OrderMonitoringResource\Pages\ListOrderMonitoring;
use Weba\ProductionPriority\Models\Criterion;
use Weba\ProductionPriority\Models\Evaluation;
use Weba\ProductionPriority\Models\OrderAttribute;
use Weba\ProductionPriority\Services\EvaluationService;
use Webkul\Manufacturing\Enums\ManufacturingOrderPriority;
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

    $this->admin = ManufacturingHelper::actingAsAdmin();

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->company = ManufacturingHelper::company();
    $this->warehouse = InventoryHelper::warehouse();
    $this->finished = ManufacturingHelper::product();
    $this->component = ManufacturingHelper::product();
});

function pageOrder(object $test, int $deadlineInDays = 5): Order
{
    $bom = ManufacturingHelper::bom($test->finished, [[$test->component, 2]]);
    $order = ManufacturingHelper::confirm(ManufacturingHelper::order($test->warehouse, $test->finished, $bom, 5));

    DB::table('manufacturing_orders')->where('id', $order->id)->update(['deadline_at' => now()->addDays($deadlineInDays)]);

    return $order->refresh();
}

function companyCriteria(object $test)
{
    return Criterion::withoutGlobalScopes()->where('company_id', $test->company->id);
}

describe('criteria', function () {
    it('lists the criteria and confirms the total weight', function () {
        Livewire::test(ListCriteria::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(companyCriteria($this)->get())
            ->assertSee('SAW is ready to run');
    });

    it('warns when the active weights do not add up to one', function () {
        companyCriteria($this)->where('code', 'C6')->update(['weight' => 0.5]);

        Livewire::test(ListCriteria::class)
            ->assertSuccessful()
            ->assertSee('must be exactly 1');
    });

    it('normalizes the weights to exactly one', function () {
        companyCriteria($this)->where('code', 'C6')->update(['weight' => 0.5]);

        Livewire::test(ListCriteria::class)->callAction('normalizeWeights')->assertNotified();

        expect(round((float) companyCriteria($this)->active()->sum('weight'), 4))->toBe(1.0);
    });

    it('creates a criterion', function () {
        Livewire::test(CreateCriterion::class)
            ->fillForm([
                'code'      => 'C7',
                'name'      => 'Kriteria uji',
                'type'      => CriterionType::BENEFIT->value,
                'source'    => CriterionSource::PROGRESS->value,
                'weight'    => 0.05,
                'is_active' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $criterion = companyCriteria($this)->where('code', 'C7')->first();

        expect($criterion)->not->toBeNull()
            ->and($criterion->company_id)->toBe($this->company->id)
            ->and($criterion->is_active)->toBeFalse();
    });

    it('rejects a duplicate code and a weight above one', function () {
        Livewire::test(CreateCriterion::class)
            ->fillForm([
                'code'   => 'C1',
                'name'   => 'Duplikat',
                'type'   => CriterionType::COST->value,
                'source' => CriterionSource::DEADLINE_DAYS->value,
                'weight' => 2,
            ])
            ->call('create')
            ->assertHasFormErrors(['code', 'weight']);
    });

    it('edits a criterion', function () {
        $criterion = companyCriteria($this)->where('code', 'C2')->firstOrFail();

        Livewire::test(EditCriterion::class, ['record' => $criterion->getKey()])
            ->fillForm(['name' => 'Urgensi diubah', 'weight' => 0.25])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($criterion->fresh()->name)->toBe('Urgensi diubah')
            ->and($criterion->fresh()->weight)->toBe('0.2500');
    });
});

describe('evaluations', function () {
    it('runs SAW from the list page and stores the result', function () {
        $order = pageOrder($this);

        Livewire::test(ListEvaluations::class)
            ->callAction('runEvaluation', ['name' => 'Evaluasi dari UI'])
            ->assertNotified();

        $evaluation = Evaluation::withoutGlobalScopes()->where('name', 'Evaluasi dari UI')->first();

        expect($evaluation)->not->toBeNull()
            ->and($evaluation->items->pluck('manufacturing_order_id')->all())->toBe([$order->id]);
    });

    it('does not store anything when there is nothing to rank', function () {
        $before = Evaluation::withoutGlobalScopes()->count();

        Livewire::test(ListEvaluations::class)->callAction('runEvaluation')->assertNotified();

        expect(Evaluation::withoutGlobalScopes()->count())->toBe($before);
    });

    it('refuses to run with invalid weights and tells the user why', function () {
        pageOrder($this);

        companyCriteria($this)->where('code', 'C6')->update(['weight' => 0.5]);

        $before = Evaluation::withoutGlobalScopes()->count();

        Livewire::test(ListEvaluations::class)->callAction('runEvaluation')->assertNotified();

        expect(Evaluation::withoutGlobalScopes()->count())->toBe($before);
    });

    it('lists the evaluations', function () {
        pageOrder($this);

        $evaluation = app(EvaluationService::class)->run($this->company->id);

        Livewire::test(ListEvaluations::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$evaluation]);
    });

    it('shows the ranking, both matrices and the criteria of an evaluation', function () {
        $order = pageOrder($this);

        $evaluation = app(EvaluationService::class)->run($this->company->id);

        Livewire::test(ViewEvaluation::class, ['record' => $evaluation->getKey()])
            ->assertSuccessful()
            ->assertSee($order->name)
            ->assertSee('Score & Ranking')
            ->assertSee('Decision Matrix')
            ->assertSee('Normalized Matrix')
            ->assertSee('C1 · Sisa waktu menuju deadline (hari)');
    });

    it('applies and reverts the priority from the view page', function () {
        $order = pageOrder($this);

        $evaluation = app(EvaluationService::class)->run($this->company->id);

        Livewire::test(ViewEvaluation::class, ['record' => $evaluation->getKey()])
            ->callAction('applyPriority', ['top_n' => 1, 'reason' => 'Pelanggan penting'])
            ->assertNotified();

        expect(Order::withoutGlobalScopes()->find($order->id)->priority)->toBe(ManufacturingOrderPriority::URGENT)
            ->and($evaluation->fresh()->isApplied())->toBeTrue();

        Livewire::test(ViewEvaluation::class, ['record' => $evaluation->getKey()])
            ->assertActionVisible('revertPriority')
            ->assertActionHidden('applyPriority')
            ->callAction('revertPriority')
            ->assertNotified();

        expect(Order::withoutGlobalScopes()->find($order->id)->priority)->toBe(ManufacturingOrderPriority::NORMAL)
            ->and($evaluation->fresh()->isApplied())->toBeFalse();
    });

    it('rejects a number of orders above the evaluated orders', function () {
        pageOrder($this);

        $evaluation = app(EvaluationService::class)->run($this->company->id);

        Livewire::test(ViewEvaluation::class, ['record' => $evaluation->getKey()])
            ->callAction('applyPriority', ['top_n' => 5])
            ->assertHasActionErrors(['top_n']);
    });
});

describe('order monitoring', function () {
    it('lists the open orders with their progress', function () {
        $order = pageOrder($this);
        $draft = ManufacturingHelper::order($this->warehouse, $this->finished, ManufacturingHelper::bom($this->finished, [[$this->component, 1]]), 1);

        Livewire::test(ListOrderMonitoring::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$order])
            ->assertCanNotSeeTableRecords([$draft]);
    });

    it('filters the late orders', function () {
        $late = pageOrder($this, -2);
        $upcoming = pageOrder($this, 10);

        Livewire::test(ListOrderMonitoring::class)
            ->filterTable('late')
            ->assertCanSeeTableRecords([$late])
            ->assertCanNotSeeTableRecords([$upcoming]);
    });

    it('saves the urgency and the order value from the table', function () {
        $order = pageOrder($this);

        Livewire::test(ListOrderMonitoring::class)
            ->callTableAction('editAttributes', $order, ['urgency_level' => 5, 'order_value' => 250000000, 'notes' => 'VIP'])
            ->assertHasNoTableActionErrors();

        $attribute = OrderAttribute::query()->where('manufacturing_order_id', $order->id)->first();

        expect($attribute->urgency_level)->toBe(5)
            ->and($attribute->order_value)->toBe('250000000.00')
            ->and($attribute->notes)->toBe('VIP');
    });

    it('updates the existing attributes instead of adding another row', function () {
        $order = pageOrder($this);

        OrderAttribute::factory()->urgency(2)->create(['manufacturing_order_id' => $order->id]);

        Livewire::test(ListOrderMonitoring::class)
            ->callTableAction('editAttributes', $order, ['urgency_level' => 4]);

        expect(OrderAttribute::query()->where('manufacturing_order_id', $order->id)->count())->toBe(1)
            ->and(OrderAttribute::query()->where('manufacturing_order_id', $order->id)->value('urgency_level'))->toBe(4);
    });

    it('shows the rank of the latest evaluation', function () {
        $order = pageOrder($this);

        app(EvaluationService::class)->run($this->company->id);

        Livewire::test(ListOrderMonitoring::class)
            ->assertSuccessful()
            ->assertSee('#1');
    });
});

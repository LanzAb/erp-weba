<?php

use Illuminate\Support\Facades\Auth;
use Weba\ProductionPriority\Database\Seeders\CriterionSeeder;
use Weba\ProductionPriority\Enums\CriterionType;
use Weba\ProductionPriority\Models\Criterion;
use Weba\ProductionPriority\Services\SawCalculator;
use Webkul\Security\Models\User;
use Webkul\Support\Models\Company;

require_once __DIR__.'/../../../../webkul/support/tests/Helpers/TestBootstrapHelper.php';

beforeEach(function () {
    TestBootstrapHelper::ensurePluginInstalled('production-priority');

    (new CriterionSeeder)->run();

    Auth::login(User::query()->firstOrFail());

    $this->company = Company::query()->firstOrFail();
});

function activeCriteriaFor(Company $company)
{
    return Criterion::withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->active()
        ->orderBy('sort')
        ->get();
}

it('seeds the six default criteria with weights that add up to one', function () {
    $criteria = activeCriteriaFor($this->company);

    expect($criteria->pluck('code')->all())->toBe(['C1', 'C2', 'C3', 'C4', 'C5', 'C6'])
        ->and(round((float) $criteria->sum('weight'), 4))->toBe(1.0);
});

it('does not duplicate criteria when the seeder runs again', function () {
    $before = Criterion::withoutGlobalScopes()->where('company_id', $this->company->id)->count();

    (new CriterionSeeder)->run();

    expect(Criterion::withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe($before);
});

it('casts type, source and weight', function () {
    $criterion = Criterion::factory()->cost()->weight(0.25)->create(['company_id' => $this->company->id]);

    expect($criterion->fresh()->type)->toBe(CriterionType::COST)
        ->and($criterion->fresh()->weight)->toBe('0.2500')
        ->and($criterion->is_active)->toBeTrue();
});

it('excludes inactive criteria from the active scope', function () {
    $inactive = Criterion::factory()->inactive()->create(['company_id' => $this->company->id]);

    expect(activeCriteriaFor($this->company)->pluck('id'))->not->toContain($inactive->id);
});

it('converts a criterion into a SAW criterion', function () {
    $criterion = Criterion::factory()->benefit()->weight(0.4)->create([
        'company_id' => $this->company->id,
        'code'       => 'T1',
    ]);

    $sawCriterion = $criterion->toSawCriterion();

    expect($sawCriterion->code)->toBe('T1')
        ->and($sawCriterion->type)->toBe(CriterionType::BENEFIT)
        ->and($sawCriterion->weight)->toBe(0.4);
});

it('feeds the seeded criteria into the SAW calculator', function () {
    $criteria = activeCriteriaFor($this->company)
        ->map(fn (Criterion $criterion) => $criterion->toSawCriterion())
        ->all();

    $result = (new SawCalculator)->calculate([
        1 => ['C1' => 2, 'C2' => 5, 'C3' => 100, 'C4' => 8, 'C5' => 80, 'C6' => 500000000],
        2 => ['C1' => 20, 'C2' => 1, 'C3' => 20, 'C4' => 40, 'C5' => 5, 'C6' => 50000000],
    ], $criteria);

    expect($result->ranking)->toBe([1, 2])
        ->and($result->scores[1])->toBe(1.0);
});

it('records a chatter activity when the weight changes', function () {
    $criterion = Criterion::factory()->create(['company_id' => $this->company->id, 'weight' => 0.1])->fresh();

    $before = $criterion->messages()->count();

    $criterion->update(['weight' => 0.2]);

    expect($criterion->messages()->count())->toBeGreaterThan($before);
});

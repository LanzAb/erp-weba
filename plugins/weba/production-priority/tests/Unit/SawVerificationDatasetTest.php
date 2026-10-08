<?php

use Weba\ProductionPriority\Enums\CriterionType;
use Weba\ProductionPriority\Services\SawCalculator;
use Weba\ProductionPriority\Support\SawCriterion;

/**
 * Cases in Fixtures/saw_cases.json were computed with an independent
 * exact-arithmetic (fractions) implementation, not with SawCalculator.
 */
dataset('saw verification cases', function () {
    $cases = json_decode(
        file_get_contents(__DIR__.'/../Fixtures/saw_cases.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    foreach ($cases as $case) {
        yield $case['name'] => [$case];
    }
});

it('matches the independently computed scores and ranking', function (array $case) {
    $criteria = array_map(
        fn (array $criterion): SawCriterion => new SawCriterion(
            $criterion['code'],
            CriterionType::from($criterion['type']),
            (float) $criterion['weight'],
        ),
        $case['criteria'],
    );

    $result = (new SawCalculator)->calculate($case['alternatives'], $criteria, $case['tie_breakers']);

    expect($result->ranking)->toBe($case['expected_ranking']);

    foreach ($case['expected_scores'] as $alternative => $expectedScore) {
        expect($result->scores[$alternative])->toEqualWithDelta($expectedScore, 0.000001);
    }
})->with('saw verification cases');

it('has at least ten verification cases', function () {
    $cases = json_decode(file_get_contents(__DIR__.'/../Fixtures/saw_cases.json'), true, flags: JSON_THROW_ON_ERROR);

    expect(count($cases))->toBeGreaterThanOrEqual(10);
});

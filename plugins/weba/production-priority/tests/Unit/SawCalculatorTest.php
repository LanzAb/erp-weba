<?php

use Weba\ProductionPriority\Enums\CriterionType;
use Weba\ProductionPriority\Services\SawCalculator;
use Weba\ProductionPriority\Support\SawCriterion;

function sawCriterion(string $code, CriterionType $type, float $weight): SawCriterion
{
    return new SawCriterion($code, $type, $weight);
}

beforeEach(function () {
    $this->calculator = new SawCalculator;
});

describe('PRD worked example', function () {
    it('ranks A > B > C with scores 0.84, 0.64 and 0.2', function () {
        $result = $this->calculator->calculate(
            [
                'A' => ['C1' => 2, 'C2' => 3],
                'B' => ['C1' => 5, 'C2' => 5],
                'C' => ['C1' => 10, 'C2' => 1],
            ],
            [
                sawCriterion('C1', CriterionType::COST, 0.6),
                sawCriterion('C2', CriterionType::BENEFIT, 0.4),
            ],
        );

        expect($result->scores['A'])->toBe(0.84)
            ->and($result->scores['B'])->toBe(0.64)
            ->and($result->scores['C'])->toBe(0.2)
            ->and($result->ranking)->toBe(['A', 'B', 'C'])
            ->and($result->ranks)->toBe(['A' => 1, 'B' => 2, 'C' => 3]);
    });

    it('exposes the normalized matrix', function () {
        $result = $this->calculator->calculate(
            [
                'A' => ['C1' => 2, 'C2' => 3],
                'B' => ['C1' => 5, 'C2' => 5],
                'C' => ['C1' => 10, 'C2' => 1],
            ],
            [
                sawCriterion('C1', CriterionType::COST, 0.6),
                sawCriterion('C2', CriterionType::BENEFIT, 0.4),
            ],
        );

        expect($result->normalized['A']['C1'])->toBe(1.0)
            ->and($result->normalized['B']['C1'])->toBe(0.4)
            ->and($result->normalized['C']['C1'])->toBe(0.2)
            ->and($result->normalized['A']['C2'])->toBe(0.6)
            ->and($result->normalized['B']['C2'])->toBe(1.0)
            ->and($result->normalized['C']['C2'])->toBe(0.2);
    });
});

describe('edge cases', function () {
    it('returns an empty result when there are no alternatives', function () {
        $result = $this->calculator->calculate([], [sawCriterion('A', CriterionType::BENEFIT, 1.0)]);

        expect($result->scores)->toBe([])
            ->and($result->ranking)->toBe([]);
    });

    it('scores a single alternative as the total weight and ranks it first', function () {
        $result = $this->calculator->calculate(
            [7 => ['A' => 0, 'B' => 0]],
            [sawCriterion('A', CriterionType::BENEFIT, 0.6), sawCriterion('B', CriterionType::COST, 0.4)],
        );

        expect($result->scores[7])->toBe(1.0)
            ->and($result->ranks[7])->toBe(1);
    });

    it('gives a benefit column that is entirely zero a normalized value of zero', function () {
        $result = $this->calculator->calculate(
            [1 => ['A' => 0, 'B' => 5], 2 => ['A' => 0, 'B' => 10]],
            [sawCriterion('A', CriterionType::BENEFIT, 0.7), sawCriterion('B', CriterionType::BENEFIT, 0.3)],
        );

        expect($result->normalized[1]['A'])->toBe(0.0)
            ->and($result->normalized[2]['A'])->toBe(0.0);
    });

    it('gives a cost value of zero a normalized value of one', function () {
        $result = $this->calculator->calculate(
            [1 => ['C4' => 0], 2 => ['C4' => 4]],
            [sawCriterion('C4', CriterionType::COST, 1.0)],
        );

        expect($result->normalized[1]['C4'])->toBe(1.0)
            ->and($result->normalized[2]['C4'])->toBe(0.0);
    });

    it('replaces a missing cost value with the largest value of the column and warns', function () {
        $result = $this->calculator->calculate(
            [1 => ['C1' => 2], 2 => ['C1' => null], 3 => ['C1' => 8]],
            [sawCriterion('C1', CriterionType::COST, 1.0)],
        );

        expect($result->normalized[2]['C1'])->toBe(0.25)
            ->and($result->warnings)->toBe([
                ['alternative' => 2, 'criterion' => 'C1', 'reason' => SawCalculator::MISSING_VALUE],
            ]);
    });

    it('replaces a missing benefit value with zero', function () {
        $result = $this->calculator->calculate(
            [1 => ['A' => 6], 2 => ['A' => null]],
            [sawCriterion('A', CriterionType::BENEFIT, 1.0)],
        );

        expect($result->normalized[2]['A'])->toBe(0.0);
    });

    it('treats a criterion that is absent from an alternative as missing', function () {
        $result = $this->calculator->calculate(
            [1 => ['A' => 6], 2 => []],
            [sawCriterion('A', CriterionType::BENEFIT, 1.0)],
        );

        expect($result->warnings)->toHaveCount(1);
    });

    it('breaks equal scores with the lowest tie breaker, such as the earliest deadline', function () {
        $result = $this->calculator->calculate(
            [1 => ['A' => 5], 2 => ['A' => 5], 3 => ['A' => 5]],
            [sawCriterion('A', CriterionType::BENEFIT, 1.0)],
            [1 => 300, 2 => 100, 3 => 200],
        );

        expect($result->ranking)->toBe([2, 3, 1]);
    });

    it('ranks alternatives without a tie breaker after those that have one', function () {
        $result = $this->calculator->calculate(
            [1 => ['A' => 5], 2 => ['A' => 5]],
            [sawCriterion('A', CriterionType::BENEFIT, 1.0)],
            [2 => 100],
        );

        expect($result->ranking)->toBe([2, 1]);
    });

    it('falls back to the smallest identifier when scores and tie breakers are equal', function () {
        $result = $this->calculator->calculate(
            [9 => ['A' => 4], 3 => ['A' => 4], 6 => ['A' => 4]],
            [sawCriterion('A', CriterionType::BENEFIT, 1.0)],
        );

        expect($result->ranking)->toBe([3, 6, 9]);
    });

    it('rounds scores to six decimals', function () {
        $result = $this->calculator->calculate(
            [1 => ['A' => 1], 2 => ['A' => 3]],
            [sawCriterion('A', CriterionType::BENEFIT, 1.0)],
        );

        expect($result->scores[1])->toBe(0.333333);
    });
});

describe('input validation', function () {
    it('rejects an empty criteria list', function () {
        $this->calculator->calculate([1 => ['A' => 1]], []);
    })->throws(InvalidArgumentException::class, 'At least one active criterion');

    it('rejects duplicate criterion codes', function () {
        $this->calculator->calculate([1 => ['A' => 1]], [
            sawCriterion('A', CriterionType::BENEFIT, 0.5),
            sawCriterion('A', CriterionType::COST, 0.5),
        ]);
    })->throws(InvalidArgumentException::class, 'Duplicate criterion code [A]');

    it('rejects a negative weight', function () {
        $this->calculator->validateCriteria([
            sawCriterion('A', CriterionType::BENEFIT, 1.5),
            sawCriterion('B', CriterionType::COST, -0.5),
        ]);
    })->throws(InvalidArgumentException::class, 'must be zero or greater');

    it('rejects weights that do not add up to one', function () {
        $this->calculator->calculate([1 => ['A' => 1]], [
            sawCriterion('A', CriterionType::BENEFIT, 0.5),
            sawCriterion('B', CriterionType::COST, 0.4),
        ]);
    })->throws(InvalidArgumentException::class, 'must be 1, got 0.9');

    it('accepts weights that add up to one within tolerance', function () {
        $this->calculator->validateCriteria([
            sawCriterion('A', CriterionType::BENEFIT, 0.3333),
            sawCriterion('B', CriterionType::COST, 0.3333),
            sawCriterion('C', CriterionType::COST, 0.3334),
        ]);

        expect(true)->toBeTrue();
    });

    it('rejects a negative value', function () {
        $this->calculator->calculate([1 => ['A' => -1]], [sawCriterion('A', CriterionType::BENEFIT, 1.0)]);
    })->throws(InvalidArgumentException::class, 'non-negative number');

    it('rejects a non numeric value', function () {
        $this->calculator->calculate([1 => ['A' => 'abc']], [sawCriterion('A', CriterionType::BENEFIT, 1.0)]);
    })->throws(InvalidArgumentException::class, 'non-negative number');
});

describe('weight normalization', function () {
    it('scales weights proportionally so they add up to one', function () {
        $normalized = $this->calculator->normalizeWeights([
            sawCriterion('A', CriterionType::BENEFIT, 3.0),
            sawCriterion('B', CriterionType::COST, 1.0),
        ]);

        expect($normalized[0]->weight)->toBe(0.75)
            ->and($normalized[1]->weight)->toBe(0.25)
            ->and($normalized[0]->code)->toBe('A')
            ->and($normalized[1]->type)->toBe(CriterionType::COST);
    });

    it('rejects normalizing when the total weight is zero', function () {
        $this->calculator->normalizeWeights([sawCriterion('A', CriterionType::BENEFIT, 0.0)]);
    })->throws(InvalidArgumentException::class, 'greater than zero');
});

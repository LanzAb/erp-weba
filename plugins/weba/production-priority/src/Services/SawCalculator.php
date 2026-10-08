<?php

namespace Weba\ProductionPriority\Services;

use InvalidArgumentException;
use Weba\ProductionPriority\Enums\CriterionType;
use Weba\ProductionPriority\Support\SawCriterion;
use Weba\ProductionPriority\Support\SawResult;

/**
 * Simple Additive Weighting (SAW).
 *
 * Normalization: benefit r = x / max(x); cost r = min(x) / x.
 * Preference score: V = sum(weight * r). Rank 1 is the highest priority.
 *
 * This class is intentionally free of database and framework access so the
 * calculation can be unit tested and verified against manual calculations.
 */
final class SawCalculator
{
    public const SCORE_PRECISION = 6;

    public const WEIGHT_TOLERANCE = 0.0001;

    public const MISSING_VALUE = 'missing_value';

    /**
     * @param  array<int|string, array<string, int|float|string|null>>  $alternatives  alternative => criterion code => raw value
     * @param  list<SawCriterion>  $criteria  active criteria only
     * @param  array<int|string, int|float|null>  $tieBreakers  alternative => sortable value, lower wins when scores are equal
     */
    public function calculate(array $alternatives, array $criteria, array $tieBreakers = []): SawResult
    {
        $this->validateCriteria($criteria);

        if ($alternatives === []) {
            return new SawResult([], [], [], []);
        }

        [$matrix, $warnings] = $this->buildDecisionMatrix($alternatives, $criteria);

        $normalized = $this->normalize($matrix, $criteria);

        $scores = $this->score($normalized, $criteria);

        [$ranking, $ranks] = $this->rank($scores, $tieBreakers);

        return new SawResult($normalized, $scores, $ranks, $ranking, $warnings);
    }

    /**
     * @param  list<SawCriterion>  $criteria
     *
     * @throws InvalidArgumentException
     */
    public function validateCriteria(array $criteria): void
    {
        if ($criteria === []) {
            throw new InvalidArgumentException('At least one active criterion is required.');
        }

        $codes = [];
        $totalWeight = 0.0;

        foreach ($criteria as $criterion) {
            if (in_array($criterion->code, $codes, true)) {
                throw new InvalidArgumentException("Duplicate criterion code [{$criterion->code}].");
            }

            if (! is_finite($criterion->weight) || $criterion->weight < 0) {
                throw new InvalidArgumentException("Weight of criterion [{$criterion->code}] must be zero or greater.");
            }

            $codes[] = $criterion->code;
            $totalWeight += $criterion->weight;
        }

        if (abs($totalWeight - 1.0) > self::WEIGHT_TOLERANCE) {
            throw new InvalidArgumentException(
                sprintf('The total weight of active criteria must be 1, got %s.', rtrim(rtrim(number_format($totalWeight, 6, '.', ''), '0'), '.'))
            );
        }
    }

    /**
     * Scale the weights proportionally so that they add up to 1.
     *
     * @param  list<SawCriterion>  $criteria
     * @return list<SawCriterion>
     *
     * @throws InvalidArgumentException
     */
    public function normalizeWeights(array $criteria): array
    {
        $total = array_sum(array_map(fn (SawCriterion $criterion): float => $criterion->weight, $criteria));

        if ($total <= 0) {
            throw new InvalidArgumentException('The total weight must be greater than zero to be normalized.');
        }

        return array_map(
            fn (SawCriterion $criterion): SawCriterion => new SawCriterion($criterion->code, $criterion->type, $criterion->weight / $total),
            $criteria,
        );
    }

    /**
     * A missing value is treated as the worst possible value of its column:
     * the largest value for a cost criterion and zero for a benefit criterion.
     *
     * @param  array<int|string, array<string, int|float|string|null>>  $alternatives
     * @param  list<SawCriterion>  $criteria
     * @return array{0: array<int|string, array<string, float>>, 1: list<array{alternative: int|string, criterion: string, reason: string}>}
     */
    protected function buildDecisionMatrix(array $alternatives, array $criteria): array
    {
        $matrix = [];
        $warnings = [];

        foreach ($criteria as $criterion) {
            $present = [];
            $missing = [];

            foreach ($alternatives as $alternative => $values) {
                $value = $values[$criterion->code] ?? null;

                if ($value === null) {
                    $missing[] = $alternative;

                    continue;
                }

                $present[$alternative] = $this->toNonNegativeFloat($value, $alternative, $criterion->code);
            }

            $fallback = $criterion->type === CriterionType::COST && $present !== [] ? max($present) : 0.0;

            foreach ($missing as $alternative) {
                $present[$alternative] = $fallback;

                $warnings[] = [
                    'alternative' => $alternative,
                    'criterion'   => $criterion->code,
                    'reason'      => self::MISSING_VALUE,
                ];
            }

            foreach (array_keys($alternatives) as $alternative) {
                $matrix[$alternative][$criterion->code] = $present[$alternative];
            }
        }

        return [$matrix, $warnings];
    }

    /**
     * @param  array<int|string, array<string, float>>  $matrix
     * @param  list<SawCriterion>  $criteria
     * @return array<int|string, array<string, float>>
     */
    protected function normalize(array $matrix, array $criteria): array
    {
        $normalized = [];
        $isSingleAlternative = count($matrix) === 1;

        foreach ($criteria as $criterion) {
            $column = array_column($matrix, $criterion->code);
            $max = max($column);
            $min = min($column);

            foreach ($matrix as $alternative => $values) {
                $value = $values[$criterion->code];

                $normalized[$alternative][$criterion->code] = match (true) {
                    $isSingleAlternative                        => 1.0,
                    $criterion->type === CriterionType::BENEFIT => $max > 0 ? $value / $max : 0.0,
                    default                                     => $value == 0.0 ? 1.0 : $min / $value,
                };
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int|string, array<string, float>>  $normalized
     * @param  list<SawCriterion>  $criteria
     * @return array<int|string, float>
     */
    protected function score(array $normalized, array $criteria): array
    {
        $scores = [];

        foreach ($normalized as $alternative => $values) {
            $total = 0.0;

            foreach ($criteria as $criterion) {
                $total += $criterion->weight * $values[$criterion->code];
            }

            $scores[$alternative] = round($total, self::SCORE_PRECISION);
        }

        return $scores;
    }

    /**
     * Highest score first; ties are broken by the lowest tie-breaker value
     * (for example the earliest deadline) and then by the smallest identifier.
     *
     * @param  array<int|string, float>  $scores
     * @param  array<int|string, int|float|null>  $tieBreakers
     * @return array{0: list<int|string>, 1: array<int|string, int>}
     */
    protected function rank(array $scores, array $tieBreakers): array
    {
        $alternatives = array_keys($scores);

        usort($alternatives, function (int|string $first, int|string $second) use ($scores, $tieBreakers): int {
            return ($scores[$second] <=> $scores[$first])
                ?: (($tieBreakers[$first] ?? PHP_FLOAT_MAX) <=> ($tieBreakers[$second] ?? PHP_FLOAT_MAX))
                ?: ($first <=> $second);
        });

        $ranks = [];

        foreach ($alternatives as $position => $alternative) {
            $ranks[$alternative] = $position + 1;
        }

        return [$alternatives, $ranks];
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function toNonNegativeFloat(mixed $value, int|string $alternative, string $criterionCode): float
    {
        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0) {
            throw new InvalidArgumentException(
                "Value of criterion [{$criterionCode}] for alternative [{$alternative}] must be a non-negative number."
            );
        }

        return (float) $value;
    }
}

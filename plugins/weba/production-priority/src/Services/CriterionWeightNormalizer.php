<?php

namespace Weba\ProductionPriority\Services;

use InvalidArgumentException;

/**
 * Scales criterion weights proportionally so that they add up to exactly 1
 * with four decimals, which is the precision stored in the database.
 */
final class CriterionWeightNormalizer
{
    public const PRECISION = 4;

    /**
     * The rounding remainder is added to the largest weight so the total is exactly 1.
     *
     * @param  array<int|string, int|float>  $weights  criterion identifier => weight
     * @return array<int|string, float>
     *
     * @throws InvalidArgumentException when there is nothing to normalize
     */
    public function normalize(array $weights): array
    {
        $total = array_sum($weights);

        if ($weights === [] || $total <= 0) {
            throw new InvalidArgumentException('The total weight must be greater than zero to be normalized.');
        }

        $normalized = array_map(
            fn (int|float $weight): float => round($weight / $total, self::PRECISION),
            $weights,
        );

        $remainder = round(1 - array_sum($normalized), self::PRECISION);

        if ($remainder !== 0.0) {
            $largest = array_search(max($normalized), $normalized, true);

            $normalized[$largest] = round($normalized[$largest] + $remainder, self::PRECISION);
        }

        return $normalized;
    }
}

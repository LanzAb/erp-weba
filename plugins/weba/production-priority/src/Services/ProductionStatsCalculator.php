<?php

namespace Weba\ProductionPriority\Services;

use Weba\ProductionPriority\Support\OrderProgress;

/**
 * Dashboard figures computed from the progress of the open orders.
 */
final class ProductionStatsCalculator
{
    public const DUE_SOON_DAYS = 3;

    /**
     * An order is "due soon" when it is not late yet and its deadline is within
     * the given number of days; late orders are counted separately.
     *
     * @param  iterable<OrderProgress>  $progresses
     * @return array{total: int, late: int, due_soon: int, average_progress: float, material_short: int}
     */
    public function summarize(iterable $progresses, int $dueSoonDays = self::DUE_SOON_DAYS): array
    {
        $total = 0;
        $late = 0;
        $dueSoon = 0;
        $materialShort = 0;
        $progressSum = 0.0;

        foreach ($progresses as $progress) {
            $total++;
            $progressSum += $progress->progressPercent;

            if ($progress->isLate) {
                $late++;
            } elseif ($progress->daysToDeadline !== null && $progress->daysToDeadline <= $dueSoonDays) {
                $dueSoon++;
            }

            if ($progress->materialAvailabilityPercent < 100.0) {
                $materialShort++;
            }
        }

        return [
            'total'            => $total,
            'late'             => $late,
            'due_soon'         => $dueSoon,
            'average_progress' => $total === 0 ? 0.0 : round($progressSum / $total, 2),
            'material_short'   => $materialShort,
        ];
    }
}

<?php

namespace Weba\ProductionPriority\Services;

use Carbon\CarbonInterface;
use Weba\ProductionPriority\Support\MaterialMoveSnapshot;
use Weba\ProductionPriority\Support\WorkOrderSnapshot;
use Webkul\Inventory\Enums\MoveState;
use Webkul\Manufacturing\Enums\ManufacturingOrderState;
use Webkul\Manufacturing\Enums\WorkOrderState;

/**
 * Production monitoring metrics computed from plain snapshots, without any
 * database access, so the rules can be unit tested.
 */
final class OrderProgressCalculator
{
    /**
     * Completion in percent. Work orders are weighted by their expected
     * duration; without work orders the produced quantity is used.
     *
     * @param  list<WorkOrderSnapshot>  $workOrders
     */
    public function progressPercent(
        ManufacturingOrderState $state,
        array $workOrders,
        float $quantity,
        float $quantityProduced,
    ): float {
        if ($state === ManufacturingOrderState::DONE) {
            return 100.0;
        }

        if ($state === ManufacturingOrderState::CANCEL) {
            return 0.0;
        }

        $workOrders = $this->withoutCanceled($workOrders);

        if ($workOrders !== []) {
            return $this->workOrderProgressPercent($workOrders);
        }

        if ($quantity <= 0) {
            return 0.0;
        }

        return $this->percent(min($quantityProduced / $quantity, 1.0));
    }

    /**
     * Minutes of work that are still to be done on the unfinished work orders.
     *
     * @param  list<WorkOrderSnapshot>  $workOrders
     */
    public function remainingMinutes(ManufacturingOrderState $state, array $workOrders): float
    {
        if (in_array($state, [ManufacturingOrderState::DONE, ManufacturingOrderState::CANCEL], true)) {
            return 0.0;
        }

        $remaining = 0.0;

        foreach ($this->withoutCanceled($workOrders) as $workOrder) {
            if ($workOrder->state === WorkOrderState::DONE) {
                continue;
            }

            $remaining += max($workOrder->expectedMinutes - $workOrder->durationMinutes, 0.0);
        }

        return round($remaining, 2);
    }

    /**
     * Days left until the deadline (fractional); negative when overdue,
     * null when there is no deadline.
     */
    public function daysToDeadline(?CarbonInterface $deadline, CarbonInterface $now): ?float
    {
        if ($deadline === null) {
            return null;
        }

        return round(($deadline->getTimestamp() - $now->getTimestamp()) / 86400, 2);
    }

    /**
     * An open order is late once its deadline has passed; a finished order is
     * late when it was finished after the deadline.
     */
    public function isLate(
        ManufacturingOrderState $state,
        ?CarbonInterface $deadline,
        ?CarbonInterface $finishedAt,
        CarbonInterface $now,
    ): bool {
        if ($deadline === null || $state === ManufacturingOrderState::CANCEL) {
            return false;
        }

        if ($state === ManufacturingOrderState::DONE) {
            return $finishedAt !== null && $finishedAt->greaterThan($deadline);
        }

        return $deadline->lessThan($now);
    }

    /**
     * Average share of each component demand that is reserved or consumed.
     * An order without components counts as fully available.
     *
     * @param  list<MaterialMoveSnapshot>  $moves
     */
    public function materialAvailabilityPercent(array $moves): float
    {
        $ratios = [];

        foreach ($moves as $move) {
            if ($move->state === MoveState::CANCELED) {
                continue;
            }

            $ratios[] = match (true) {
                $move->state === MoveState::DONE => 1.0,
                $move->demandQuantity <= 0       => 1.0,
                default                          => min(max($move->reservedQuantity, 0.0) / $move->demandQuantity, 1.0),
            };
        }

        if ($ratios === []) {
            return 100.0;
        }

        return $this->percent(array_sum($ratios) / count($ratios));
    }

    /**
     * @param  list<WorkOrderSnapshot>  $workOrders
     * @return list<WorkOrderSnapshot>
     */
    protected function withoutCanceled(array $workOrders): array
    {
        return array_values(array_filter(
            $workOrders,
            fn (WorkOrderSnapshot $workOrder): bool => $workOrder->state !== WorkOrderState::CANCEL,
        ));
    }

    /**
     * @param  non-empty-list<WorkOrderSnapshot>  $workOrders
     */
    protected function workOrderProgressPercent(array $workOrders): float
    {
        $totalExpected = array_sum(array_map(fn (WorkOrderSnapshot $workOrder): float => $workOrder->expectedMinutes, $workOrders));

        if ($totalExpected <= 0) {
            $done = count(array_filter($workOrders, fn (WorkOrderSnapshot $workOrder): bool => $workOrder->state === WorkOrderState::DONE));

            return $this->percent($done / count($workOrders));
        }

        $completed = 0.0;

        foreach ($workOrders as $workOrder) {
            $completed += match ($workOrder->state) {
                WorkOrderState::DONE     => $workOrder->expectedMinutes,
                WorkOrderState::PROGRESS => min($workOrder->durationMinutes, $workOrder->expectedMinutes),
                default                  => 0.0,
            };
        }

        return $this->percent($completed / $totalExpected);
    }

    protected function percent(float $ratio): float
    {
        return round(max(0.0, min($ratio, 1.0)) * 100, 2);
    }
}

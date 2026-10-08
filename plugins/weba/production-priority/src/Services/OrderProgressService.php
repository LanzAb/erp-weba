<?php

namespace Weba\ProductionPriority\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Weba\ProductionPriority\Support\MaterialMoveSnapshot;
use Weba\ProductionPriority\Support\OrderProgress;
use Weba\ProductionPriority\Support\WorkOrderSnapshot;
use Webkul\Inventory\Enums\MoveState;
use Webkul\Manufacturing\Models\Order;
use Webkul\Manufacturing\Models\WorkOrder;

/**
 * Reads the production data of manufacturing orders and turns it into
 * monitoring metrics. Relations are loaded once per batch to avoid N+1 queries.
 */
class OrderProgressService
{
    public const RELATIONS = ['workOrders', 'rawMaterialMoves', 'finishedMoves'];

    public function __construct(protected OrderProgressCalculator $calculator) {}

    public function forOrder(Order $order, ?CarbonInterface $now = null): OrderProgress
    {
        $order->loadMissing(self::RELATIONS);

        $now ??= now();

        $workOrders = $order->workOrders
            ->map(fn (WorkOrder $workOrder): WorkOrderSnapshot => new WorkOrderSnapshot(
                $workOrder->state,
                (float) $workOrder->expected_duration,
                (float) $workOrder->duration,
            ))
            ->all();

        $materialMoves = $order->rawMaterialMoves
            ->map(fn ($move): MaterialMoveSnapshot => new MaterialMoveSnapshot(
                $move->state ?? MoveState::DRAFT,
                (float) $move->product_uom_qty,
                (float) $move->quantity,
            ))
            ->all();

        return new OrderProgress(
            progressPercent: $this->calculator->progressPercent($order->state, $workOrders, (float) $order->quantity, $this->quantityProduced($order)),
            remainingMinutes: $this->calculator->remainingMinutes($order->state, $workOrders),
            daysToDeadline: $this->calculator->daysToDeadline($order->deadline_at, $now),
            isLate: $this->calculator->isLate($order->state, $order->deadline_at, $order->finished_at, $now),
            materialAvailabilityPercent: $this->calculator->materialAvailabilityPercent($materialMoves),
        );
    }

    /**
     * @param  iterable<Order>  $orders
     * @return array<int, OrderProgress> keyed by order id
     */
    public function forOrders(iterable $orders, ?CarbonInterface $now = null): array
    {
        $orders = $orders instanceof Collection ? $orders : new Collection($orders);

        $orders->loadMissing(self::RELATIONS);

        $now ??= now();

        $progress = [];

        foreach ($orders as $order) {
            $progress[$order->id] = $this->forOrder($order, $now);
        }

        return $progress;
    }

    /**
     * Picked quantity of the main product, ignoring canceled and by-product moves.
     */
    protected function quantityProduced(Order $order): float
    {
        return (float) $order->finishedMoves
            ->filter(fn ($move): bool => $move->state !== MoveState::CANCELED
                && $move->product_id === $order->product_id
                && $move->is_picked)
            ->sum('quantity');
    }
}

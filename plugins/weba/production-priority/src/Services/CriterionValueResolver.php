<?php

namespace Weba\ProductionPriority\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Weba\ProductionPriority\Enums\CriterionSource;
use Weba\ProductionPriority\Models\Criterion;
use Weba\ProductionPriority\Models\OrderAttribute;
use Weba\ProductionPriority\Support\OrderProgress;
use Webkul\Manufacturing\Models\Order;

/**
 * Builds the SAW decision matrix input: for every order the raw value of every
 * criterion, read from the production data. A null value means "unknown" and is
 * handled by SawCalculator.
 */
class CriterionValueResolver
{
    public function __construct(protected OrderProgressService $progressService) {}

    /**
     * @param  iterable<Order>  $orders
     * @param  iterable<Criterion>  $criteria
     * @return array<int, array<string, float|null>> order id => criterion code => raw value
     */
    public function resolve(iterable $orders, iterable $criteria, ?CarbonInterface $now = null): array
    {
        $orders = $orders instanceof Collection ? $orders : new Collection($orders);

        $orders->loadMissing([...OrderProgressService::RELATIONS, 'priorityAttribute']);

        $progress = $this->progressService->forOrders($orders, $now);

        $matrix = [];

        foreach ($orders as $order) {
            foreach ($criteria as $criterion) {
                $matrix[$order->id][$criterion->code] = $this->valueFor($criterion->source, $order, $progress[$order->id]);
            }
        }

        return $matrix;
    }

    /**
     * The earliest deadline wins when two orders have the same SAW score.
     *
     * @param  iterable<Order>  $orders
     * @return array<int, int|null> order id => deadline timestamp
     */
    public function tieBreakers(iterable $orders): array
    {
        $tieBreakers = [];

        foreach ($orders as $order) {
            $tieBreakers[$order->id] = $order->deadline_at?->getTimestamp();
        }

        return $tieBreakers;
    }

    protected function valueFor(CriterionSource $source, Order $order, OrderProgress $progress): ?float
    {
        /** @var OrderAttribute|null $attribute */
        $attribute = $order->priorityAttribute;

        return match ($source) {
            CriterionSource::DEADLINE_DAYS         => $progress->daysToDeadline === null ? null : max($progress->daysToDeadline, 0.0),
            CriterionSource::URGENCY_LEVEL         => (float) ($attribute?->urgency_level ?? OrderAttribute::DEFAULT_URGENCY_LEVEL),
            CriterionSource::MATERIAL_AVAILABILITY => $progress->materialAvailabilityPercent,
            CriterionSource::REMAINING_DURATION    => $progress->remainingHours(),
            CriterionSource::PROGRESS              => $progress->progressPercent,
            CriterionSource::ORDER_VALUE           => $attribute?->order_value === null ? null : (float) $attribute->order_value,
        };
    }
}

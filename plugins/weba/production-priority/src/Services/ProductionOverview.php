<?php

namespace Weba\ProductionPriority\Services;

use Illuminate\Database\Eloquent\Collection;
use Weba\ProductionPriority\Support\OrderProgress;
use Webkul\Manufacturing\Enums\ManufacturingOrderState;
use Webkul\Manufacturing\Models\Order;

/**
 * Loads the open orders once per request so every dashboard widget works on
 * the same data without repeating the queries.
 */
class ProductionOverview
{
    /**
     * @var Collection<int, Order>|null
     */
    protected ?Collection $orders = null;

    /**
     * @var array<int, OrderProgress>|null
     */
    protected ?array $progress = null;

    public function __construct(
        protected OrderProgressService $progressService,
        protected ProductionStatsCalculator $statsCalculator,
    ) {}

    /**
     * @return Collection<int, Order>
     */
    public function orders(): Collection
    {
        return $this->orders ??= Order::query()
            ->whereIn('state', array_map(
                fn (ManufacturingOrderState $state): string => $state->value,
                EvaluationService::EVALUATED_STATES,
            ))
            ->with(OrderProgressService::RELATIONS)
            ->get();
    }

    /**
     * @return array<int, OrderProgress> keyed by order id
     */
    public function progress(): array
    {
        return $this->progress ??= $this->progressService->forOrders($this->orders());
    }

    /**
     * @return array{total: int, late: int, due_soon: int, average_progress: float, material_short: int}
     */
    public function stats(): array
    {
        return $this->statsCalculator->summarize($this->progress());
    }
}

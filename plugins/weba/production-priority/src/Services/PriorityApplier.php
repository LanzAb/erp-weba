<?php

namespace Weba\ProductionPriority\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Weba\ProductionPriority\Models\Evaluation;
use Weba\ProductionPriority\Models\EvaluationItem;
use Webkul\Manufacturing\Enums\ManufacturingOrderPriority;
use Webkul\Manufacturing\Models\Order;

/**
 * Turns the top ranked orders of an evaluation into urgent orders, and undoes it.
 * Only orders whose priority was actually changed are remembered, so reverting
 * never touches an order that somebody else changed afterwards.
 */
class PriorityApplier
{
    /**
     * @return int number of orders that were changed to urgent
     *
     * @throws LogicException when the evaluation was already applied
     * @throws InvalidArgumentException when the number of orders is below one
     */
    public function apply(Evaluation $evaluation, int $topN, ?string $reason = null): int
    {
        if ($evaluation->isApplied()) {
            throw new LogicException('This evaluation was already applied. Revert it first.');
        }

        if ($topN < 1) {
            throw new InvalidArgumentException('At least one order must be selected.');
        }

        $items = $evaluation->items()->where('rank', '<=', $topN)->get();

        $orders = $this->ordersFor($items);

        return DB::transaction(function () use ($evaluation, $topN, $reason, $items, $orders): int {
            $changed = 0;

            foreach ($items as $item) {
                $order = $orders->get($item->manufacturing_order_id);

                if (! $order || $order->priority === ManufacturingOrderPriority::URGENT || ! $this->isStillOpen($order)) {
                    continue;
                }

                $previous = $order->priority?->value ?? ManufacturingOrderPriority::NORMAL->value;

                $order->updateQuietly(['priority' => ManufacturingOrderPriority::URGENT]);

                $item->update(['previous_priority' => $previous, 'applied' => true]);

                $this->note($order, 'applied', $evaluation, $reason, [
                    'rank'  => $item->rank,
                    'score' => $item->score,
                ]);

                $changed++;
            }

            $evaluation->update([
                'applied_at'    => now(),
                'applied_by'    => Auth::id(),
                'applied_top_n' => $topN,
            ]);

            return $changed;
        });
    }

    /**
     * @return int number of orders whose previous priority was restored
     *
     * @throws LogicException when the evaluation was not applied
     */
    public function revert(Evaluation $evaluation): int
    {
        if (! $evaluation->isApplied()) {
            throw new LogicException('This evaluation was not applied.');
        }

        $items = $evaluation->items()->where('applied', true)->get();

        $orders = $this->ordersFor($items);

        return DB::transaction(function () use ($evaluation, $items, $orders): int {
            $restored = 0;

            foreach ($items as $item) {
                $order = $orders->get($item->manufacturing_order_id);

                if ($order && $order->priority === ManufacturingOrderPriority::URGENT) {
                    $order->updateQuietly([
                        'priority' => ManufacturingOrderPriority::tryFrom((string) $item->previous_priority) ?? ManufacturingOrderPriority::NORMAL,
                    ]);

                    $this->note($order, 'reverted', $evaluation);

                    $restored++;
                }

                $item->update(['applied' => false]);
            }

            $evaluation->update(['applied_at' => null, 'applied_by' => null, 'applied_top_n' => null]);

            return $restored;
        });
    }

    /**
     * @param  iterable<EvaluationItem>  $items
     * @return Collection<int, Order>
     */
    protected function ordersFor(iterable $items)
    {
        $orderIds = collect($items)->pluck('manufacturing_order_id');

        return Order::withoutGlobalScopes()->whereIn('id', $orderIds)->get()->keyBy('id');
    }

    protected function isStillOpen(Order $order): bool
    {
        return in_array($order->state, EvaluationService::EVALUATED_STATES, true);
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    protected function note(Order $order, string $key, Evaluation $evaluation, ?string $reason = null, array $replace = []): void
    {
        $body = __('production-priority::services/priority-applier.'.$key, ['evaluation' => $evaluation->name, ...$replace]);

        if (filled($reason)) {
            $body .= ' '.__('production-priority::services/priority-applier.reason', ['reason' => $reason]);
        }

        $order->addMessage([
            'type'       => 'comment',
            'body'       => $body,
            'company_id' => $order->company_id,
        ]);
    }
}

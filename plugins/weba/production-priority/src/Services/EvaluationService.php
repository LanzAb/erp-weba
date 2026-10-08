<?php

namespace Weba\ProductionPriority\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Weba\ProductionPriority\Enums\EvaluationTrigger;
use Weba\ProductionPriority\Models\Criterion;
use Weba\ProductionPriority\Models\Evaluation;
use Weba\ProductionPriority\Support\SawCriterion;
use Webkul\Manufacturing\Enums\ManufacturingOrderState;
use Webkul\Manufacturing\Models\Order;

/**
 * Runs SAW for the open manufacturing orders of one company and stores the
 * result as an immutable snapshot.
 */
class EvaluationService
{
    /**
     * @var list<ManufacturingOrderState>
     */
    public const EVALUATED_STATES = [
        ManufacturingOrderState::CONFIRMED,
        ManufacturingOrderState::PROGRESS,
        ManufacturingOrderState::TO_CLOSE,
    ];

    public function __construct(
        protected CriterionValueResolver $resolver,
        protected SawCalculator $calculator,
    ) {}

    /**
     * @return Evaluation|null null when the company has no open order to rank
     *
     * @throws InvalidArgumentException when there is no active criterion or the weights do not add up to one
     */
    public function run(
        ?int $companyId = null,
        EvaluationTrigger $trigger = EvaluationTrigger::MANUAL,
        ?string $name = null,
        ?string $notes = null,
    ): ?Evaluation {
        $companyId ??= current_company_id();

        if ($companyId === null) {
            throw new InvalidArgumentException('A company is required to run an evaluation.');
        }

        $criteria = Criterion::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->active()
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $sawCriteria = $criteria->map(fn (Criterion $criterion): SawCriterion => $criterion->toSawCriterion())->all();

        $this->calculator->validateCriteria($sawCriteria);

        $orders = Order::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('state', array_map(fn (ManufacturingOrderState $state): string => $state->value, self::EVALUATED_STATES))
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        $now = now();

        $rawValues = $this->resolver->resolve($orders, $criteria, $now);

        $result = $this->calculator->calculate($rawValues, $sawCriteria, $this->resolver->tieBreakers($orders));

        return DB::transaction(function () use ($companyId, $trigger, $name, $notes, $criteria, $orders, $rawValues, $result, $now): Evaluation {
            $evaluation = Evaluation::withoutGlobalScopes()->create([
                'company_id'        => $companyId,
                'name'              => $name ?? __('production-priority::models/evaluation.default-name', ['date' => $now->format('Y-m-d H:i')]),
                'trigger'           => $trigger,
                'evaluated_at'      => $now,
                'evaluated_by'      => $trigger === EvaluationTrigger::MANUAL ? Auth::id() : null,
                'criteria_snapshot' => $criteria->map(fn (Criterion $criterion): array => [
                    'code'   => $criterion->code,
                    'name'   => $criterion->name,
                    'type'   => $criterion->type->value,
                    'source' => $criterion->source->value,
                    'weight' => (float) $criterion->weight,
                ])->all(),
                'warnings'    => $result->warnings,
                'order_count' => $orders->count(),
                'notes'       => $notes,
            ]);

            $evaluation->items()->createMany(
                array_map(fn (int|string $orderId): array => [
                    'manufacturing_order_id' => $orderId,
                    'raw_values'             => $rawValues[$orderId],
                    'normalized_values'      => $result->normalized[$orderId],
                    'score'                  => $result->scores[$orderId],
                    'rank'                   => $result->ranks[$orderId],
                ], $result->ranking)
            );

            return $evaluation;
        });
    }
}

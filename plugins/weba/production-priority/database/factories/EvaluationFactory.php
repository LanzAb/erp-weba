<?php

namespace Weba\ProductionPriority\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Weba\ProductionPriority\Enums\EvaluationTrigger;
use Weba\ProductionPriority\Models\Evaluation;
use Webkul\Support\Database\Factories\Concerns\HasCompanyDefault;

/**
 * @extends Factory<Evaluation>
 */
class EvaluationFactory extends Factory
{
    use HasCompanyDefault;

    protected $model = Evaluation::class;

    public function definition(): array
    {
        return [
            'name'              => 'Evaluasi SAW '.fake()->unique()->numerify('####'),
            'trigger'           => EvaluationTrigger::MANUAL,
            'evaluated_at'      => now(),
            'criteria_snapshot' => [],
            'warnings'          => [],
            'order_count'       => 0,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['trigger' => EvaluationTrigger::SCHEDULED]);
    }
}

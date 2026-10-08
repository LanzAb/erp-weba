<?php

namespace Weba\ProductionPriority\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Weba\ProductionPriority\Models\Evaluation;
use Weba\ProductionPriority\Models\EvaluationItem;
use Webkul\Manufacturing\Models\Order;

/**
 * @extends Factory<EvaluationItem>
 */
class EvaluationItemFactory extends Factory
{
    protected $model = EvaluationItem::class;

    public function definition(): array
    {
        return [
            'evaluation_id'          => Evaluation::factory(),
            'manufacturing_order_id' => Order::factory(),
            'raw_values'             => [],
            'normalized_values'      => [],
            'score'                  => fake()->randomFloat(6, 0, 1),
            'rank'                   => fake()->numberBetween(1, 50),
            'applied'                => false,
        ];
    }
}

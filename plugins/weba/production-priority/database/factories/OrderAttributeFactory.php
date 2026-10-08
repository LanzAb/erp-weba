<?php

namespace Weba\ProductionPriority\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Weba\ProductionPriority\Models\OrderAttribute;
use Webkul\Manufacturing\Models\Order;

/**
 * @extends Factory<OrderAttribute>
 */
class OrderAttributeFactory extends Factory
{
    protected $model = OrderAttribute::class;

    public function definition(): array
    {
        return [
            'manufacturing_order_id' => Order::factory(),
            'urgency_level'          => fake()->numberBetween(OrderAttribute::MIN_URGENCY_LEVEL, OrderAttribute::MAX_URGENCY_LEVEL),
            'order_value'            => fake()->randomFloat(2, 1_000_000, 500_000_000),
            'notes'                  => fake()->sentence(),
        ];
    }

    public function urgency(int $level): static
    {
        return $this->state(fn () => ['urgency_level' => $level]);
    }

    public function orderValue(?float $value): static
    {
        return $this->state(fn () => ['order_value' => $value]);
    }
}

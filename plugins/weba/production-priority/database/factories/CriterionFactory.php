<?php

namespace Weba\ProductionPriority\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Weba\ProductionPriority\Enums\CriterionSource;
use Weba\ProductionPriority\Enums\CriterionType;
use Weba\ProductionPriority\Models\Criterion;
use Webkul\Support\Database\Factories\Concerns\HasCompanyDefault;

/**
 * @extends Factory<Criterion>
 */
class CriterionFactory extends Factory
{
    use HasCompanyDefault;

    protected $model = Criterion::class;

    public function definition(): array
    {
        return [
            'code'      => strtoupper(fake()->unique()->lexify('K????')),
            'name'      => fake()->words(3, true),
            'type'      => fake()->randomElement(CriterionType::cases()),
            'source'    => fake()->randomElement(CriterionSource::cases()),
            'weight'    => 0.1,
            'is_active' => true,
            'sort'      => fake()->numberBetween(1, 100),
        ];
    }

    public function benefit(): static
    {
        return $this->state(fn () => ['type' => CriterionType::BENEFIT]);
    }

    public function cost(): static
    {
        return $this->state(fn () => ['type' => CriterionType::COST]);
    }

    public function weight(float $weight): static
    {
        return $this->state(fn () => ['weight' => $weight]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

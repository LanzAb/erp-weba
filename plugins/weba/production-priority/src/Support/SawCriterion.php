<?php

namespace Weba\ProductionPriority\Support;

use Weba\ProductionPriority\Enums\CriterionType;

final readonly class SawCriterion
{
    public function __construct(
        public string $code,
        public CriterionType $type,
        public float $weight,
    ) {}
}

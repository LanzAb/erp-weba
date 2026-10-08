<?php

namespace Weba\ProductionPriority\Support;

use Webkul\Inventory\Enums\MoveState;

final readonly class MaterialMoveSnapshot
{
    public function __construct(
        public MoveState $state,
        public float $demandQuantity,
        public float $reservedQuantity,
    ) {}
}

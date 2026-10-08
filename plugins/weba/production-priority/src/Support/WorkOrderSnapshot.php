<?php

namespace Weba\ProductionPriority\Support;

use Webkul\Manufacturing\Enums\WorkOrderState;

final readonly class WorkOrderSnapshot
{
    public function __construct(
        public WorkOrderState $state,
        public float $expectedMinutes,
        public float $durationMinutes,
    ) {}
}

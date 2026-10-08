<?php

namespace Weba\ProductionPriority\Support;

final readonly class OrderProgress
{
    public function __construct(
        public float $progressPercent,
        public float $remainingMinutes,
        public ?float $daysToDeadline,
        public bool $isLate,
        public float $materialAvailabilityPercent,
    ) {}

    public function remainingHours(): float
    {
        return round($this->remainingMinutes / 60, 2);
    }
}

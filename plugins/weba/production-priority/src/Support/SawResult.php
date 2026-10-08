<?php

namespace Weba\ProductionPriority\Support;

final readonly class SawResult
{
    /**
     * @param  array<int|string, array<string, float>>  $normalized  alternative => criterion code => normalized value (r)
     * @param  array<int|string, float>  $scores  alternative => preference score (V)
     * @param  array<int|string, int>  $ranks  alternative => rank (1 = highest priority)
     * @param  list<int|string>  $ranking  alternatives ordered from rank 1
     * @param  list<array{alternative: int|string, criterion: string, reason: string}>  $warnings
     */
    public function __construct(
        public array $normalized,
        public array $scores,
        public array $ranks,
        public array $ranking,
        public array $warnings = [],
    ) {}
}

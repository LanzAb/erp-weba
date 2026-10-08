<?php

use Weba\ProductionPriority\Services\CriterionWeightNormalizer;

beforeEach(function () {
    $this->normalizer = new CriterionWeightNormalizer;
});

it('keeps weights that already add up to one', function () {
    expect($this->normalizer->normalize(['C1' => 0.3, 'C2' => 0.7]))->toBe(['C1' => 0.3, 'C2' => 0.7]);
});

it('scales weights proportionally', function () {
    expect($this->normalizer->normalize(['A' => 3, 'B' => 1]))->toBe(['A' => 0.75, 'B' => 0.25]);
});

it('makes three equal weights add up to exactly one', function () {
    $normalized = $this->normalizer->normalize(['A' => 1, 'B' => 1, 'C' => 1]);

    expect(round(array_sum($normalized), 4))->toBe(1.0)
        ->and($normalized['A'])->toBe(0.3334)
        ->and($normalized['B'])->toBe(0.3333)
        ->and($normalized['C'])->toBe(0.3333);
});

it('adds up to one for awkward weights', function (array $weights) {
    expect(round(array_sum($this->normalizer->normalize($weights)), 4))->toBe(1.0);
})->with([
    'sixths'   => [['A' => 1, 'B' => 1, 'C' => 1, 'D' => 1, 'E' => 1, 'F' => 1]],
    'sevenths' => [array_fill_keys(['A', 'B', 'C', 'D', 'E', 'F', 'G'], 1)],
    'default'  => [['C1' => 0.35, 'C2' => 0.2, 'C3' => 0.15, 'C4' => 0.15, 'C5' => 0.1, 'C6' => 0.1]],
    'uneven'   => [['A' => 0.123, 'B' => 0.456, 'C' => 0.789]],
]);

it('keeps a zero weight at zero', function () {
    expect($this->normalizer->normalize(['A' => 2, 'B' => 0]))->toBe(['A' => 1.0, 'B' => 0.0]);
});

it('rejects an empty list', function () {
    $this->normalizer->normalize([]);
})->throws(InvalidArgumentException::class, 'greater than zero');

it('rejects weights that are all zero', function () {
    $this->normalizer->normalize(['A' => 0, 'B' => 0]);
})->throws(InvalidArgumentException::class, 'greater than zero');

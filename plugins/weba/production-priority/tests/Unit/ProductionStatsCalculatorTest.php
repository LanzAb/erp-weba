<?php

use Weba\ProductionPriority\Services\ProductionStatsCalculator;
use Weba\ProductionPriority\Support\OrderProgress;

function statsProgress(float $progress, ?float $days, bool $late = false, float $material = 100.0): OrderProgress
{
    return new OrderProgress($progress, 0.0, $days, $late, $material);
}

beforeEach(function () {
    $this->calculator = new ProductionStatsCalculator;
});

it('returns zeros when there are no open orders', function () {
    expect($this->calculator->summarize([]))->toBe([
        'total'            => 0,
        'late'             => 0,
        'due_soon'         => 0,
        'average_progress' => 0.0,
        'material_short'   => 0,
    ]);
});

it('counts late, due soon and material shortages', function () {
    $stats = $this->calculator->summarize([
        statsProgress(50, -2.0, late: true),
        statsProgress(10, 1.5),
        statsProgress(90, 3.0, material: 40.0),
        statsProgress(30, 12.0),
        statsProgress(0, null, material: 0.0),
    ]);

    expect($stats['total'])->toBe(5)
        ->and($stats['late'])->toBe(1)
        ->and($stats['due_soon'])->toBe(2)
        ->and($stats['material_short'])->toBe(2);
});

it('averages the progress', function () {
    $stats = $this->calculator->summarize([
        statsProgress(50, 5.0),
        statsProgress(25, 5.0),
        statsProgress(0, 5.0),
    ]);

    expect($stats['average_progress'])->toBe(25.0);
});

it('does not count a late order as due soon', function () {
    $stats = $this->calculator->summarize([statsProgress(10, -0.5, late: true)]);

    expect($stats['late'])->toBe(1)
        ->and($stats['due_soon'])->toBe(0);
});

it('does not count orders without a deadline as due soon', function () {
    expect($this->calculator->summarize([statsProgress(10, null)])['due_soon'])->toBe(0);
});

it('uses the given due soon window', function () {
    $progresses = [statsProgress(10, 5.0), statsProgress(10, 9.0)];

    expect($this->calculator->summarize($progresses, dueSoonDays: 7)['due_soon'])->toBe(1);
});

it('accepts any iterable', function () {
    $stats = $this->calculator->summarize((function () {
        yield statsProgress(40, 2.0);
        yield statsProgress(60, 2.0);
    })());

    expect($stats['total'])->toBe(2)
        ->and($stats['average_progress'])->toBe(50.0);
});

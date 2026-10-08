<?php

use Carbon\Carbon;
use Weba\ProductionPriority\Services\OrderProgressCalculator;
use Weba\ProductionPriority\Support\MaterialMoveSnapshot;
use Weba\ProductionPriority\Support\WorkOrderSnapshot;
use Webkul\Inventory\Enums\MoveState;
use Webkul\Manufacturing\Enums\ManufacturingOrderState;
use Webkul\Manufacturing\Enums\WorkOrderState;

function workOrderSnapshot(WorkOrderState $state, float $expected, float $duration = 0): WorkOrderSnapshot
{
    return new WorkOrderSnapshot($state, $expected, $duration);
}

function materialSnapshot(MoveState $state, float $demand, float $reserved): MaterialMoveSnapshot
{
    return new MaterialMoveSnapshot($state, $demand, $reserved);
}

beforeEach(function () {
    $this->calculator = new OrderProgressCalculator;
    $this->now = Carbon::parse('2026-10-08 08:00:00');
});

describe('progress percent', function () {
    it('is 100 for a done order and 0 for a canceled order', function () {
        $workOrders = [workOrderSnapshot(WorkOrderState::PENDING, 60)];

        expect($this->calculator->progressPercent(ManufacturingOrderState::DONE, $workOrders, 10, 0))->toBe(100.0)
            ->and($this->calculator->progressPercent(ManufacturingOrderState::CANCEL, $workOrders, 10, 10))->toBe(0.0);
    });

    it('weights work orders by expected duration', function () {
        $workOrders = [
            workOrderSnapshot(WorkOrderState::DONE, 60, 70),
            workOrderSnapshot(WorkOrderState::PROGRESS, 120, 30),
            workOrderSnapshot(WorkOrderState::PENDING, 20),
        ];

        // (60 + 30) / 200
        expect($this->calculator->progressPercent(ManufacturingOrderState::PROGRESS, $workOrders, 5, 0))->toBe(45.0);
    });

    it('caps a running work order at its expected duration', function () {
        $workOrders = [
            workOrderSnapshot(WorkOrderState::PROGRESS, 60, 500),
            workOrderSnapshot(WorkOrderState::PENDING, 60),
        ];

        expect($this->calculator->progressPercent(ManufacturingOrderState::PROGRESS, $workOrders, 5, 0))->toBe(50.0);
    });

    it('ignores canceled work orders', function () {
        $workOrders = [
            workOrderSnapshot(WorkOrderState::DONE, 60, 60),
            workOrderSnapshot(WorkOrderState::CANCEL, 600),
        ];

        expect($this->calculator->progressPercent(ManufacturingOrderState::PROGRESS, $workOrders, 5, 0))->toBe(100.0);
    });

    it('counts finished work orders when no expected duration is known', function () {
        $workOrders = [
            workOrderSnapshot(WorkOrderState::DONE, 0),
            workOrderSnapshot(WorkOrderState::PENDING, 0),
            workOrderSnapshot(WorkOrderState::PENDING, 0),
            workOrderSnapshot(WorkOrderState::READY, 0),
        ];

        expect($this->calculator->progressPercent(ManufacturingOrderState::PROGRESS, $workOrders, 5, 0))->toBe(25.0);
    });

    it('falls back to the produced quantity when there are no work orders', function () {
        expect($this->calculator->progressPercent(ManufacturingOrderState::PROGRESS, [], 8, 2))->toBe(25.0)
            ->and($this->calculator->progressPercent(ManufacturingOrderState::PROGRESS, [], 8, 20))->toBe(100.0)
            ->and($this->calculator->progressPercent(ManufacturingOrderState::CONFIRMED, [], 0, 0))->toBe(0.0);
    });
});

describe('remaining minutes', function () {
    it('sums what is left on unfinished work orders', function () {
        $workOrders = [
            workOrderSnapshot(WorkOrderState::DONE, 60, 60),
            workOrderSnapshot(WorkOrderState::PROGRESS, 120, 45),
            workOrderSnapshot(WorkOrderState::PENDING, 30),
            workOrderSnapshot(WorkOrderState::CANCEL, 999),
        ];

        expect($this->calculator->remainingMinutes(ManufacturingOrderState::PROGRESS, $workOrders))->toBe(105.0);
    });

    it('never goes negative when a work order overran its estimate', function () {
        $workOrders = [workOrderSnapshot(WorkOrderState::PROGRESS, 60, 90)];

        expect($this->calculator->remainingMinutes(ManufacturingOrderState::PROGRESS, $workOrders))->toBe(0.0);
    });

    it('is zero for done and canceled orders', function () {
        $workOrders = [workOrderSnapshot(WorkOrderState::PENDING, 60)];

        expect($this->calculator->remainingMinutes(ManufacturingOrderState::DONE, $workOrders))->toBe(0.0)
            ->and($this->calculator->remainingMinutes(ManufacturingOrderState::CANCEL, $workOrders))->toBe(0.0);
    });
});

describe('deadline', function () {
    it('returns the fractional days left', function () {
        expect($this->calculator->daysToDeadline($this->now->copy()->addDays(3)->addHours(12), $this->now))->toBe(3.5);
    });

    it('returns a negative value when the deadline has passed', function () {
        expect($this->calculator->daysToDeadline($this->now->copy()->subDay(), $this->now))->toBe(-1.0);
    });

    it('returns null without a deadline', function () {
        expect($this->calculator->daysToDeadline(null, $this->now))->toBeNull();
    });

    it('flags an open order as late only after the deadline', function () {
        $late = $this->now->copy()->subHour();
        $upcoming = $this->now->copy()->addHour();

        expect($this->calculator->isLate(ManufacturingOrderState::PROGRESS, $late, null, $this->now))->toBeTrue()
            ->and($this->calculator->isLate(ManufacturingOrderState::PROGRESS, $upcoming, null, $this->now))->toBeFalse();
    });

    it('flags a finished order as late when it was finished after the deadline', function () {
        $deadline = $this->now->copy()->subDays(2);

        expect($this->calculator->isLate(ManufacturingOrderState::DONE, $deadline, $deadline->copy()->addHour(), $this->now))->toBeTrue()
            ->and($this->calculator->isLate(ManufacturingOrderState::DONE, $deadline, $deadline->copy()->subHour(), $this->now))->toBeFalse()
            ->and($this->calculator->isLate(ManufacturingOrderState::DONE, $deadline, null, $this->now))->toBeFalse();
    });

    it('is never late without a deadline or when canceled', function () {
        expect($this->calculator->isLate(ManufacturingOrderState::PROGRESS, null, null, $this->now))->toBeFalse()
            ->and($this->calculator->isLate(ManufacturingOrderState::CANCEL, $this->now->copy()->subDay(), null, $this->now))->toBeFalse();
    });
});

describe('material availability', function () {
    it('averages the reserved share of each component', function () {
        $moves = [
            materialSnapshot(MoveState::ASSIGNED, 10, 10),
            materialSnapshot(MoveState::PARTIALLY_ASSIGNED, 10, 5),
            materialSnapshot(MoveState::CONFIRMED, 4, 0),
        ];

        expect($this->calculator->materialAvailabilityPercent($moves))->toBe(50.0);
    });

    it('caps over-reservation at the demand', function () {
        $moves = [materialSnapshot(MoveState::ASSIGNED, 10, 25)];

        expect($this->calculator->materialAvailabilityPercent($moves))->toBe(100.0);
    });

    it('treats done moves as available and skips canceled moves', function () {
        $moves = [
            materialSnapshot(MoveState::DONE, 10, 0),
            materialSnapshot(MoveState::CANCELED, 10, 0),
            materialSnapshot(MoveState::WAITING, 10, 0),
        ];

        expect($this->calculator->materialAvailabilityPercent($moves))->toBe(50.0);
    });

    it('treats a zero demand as available', function () {
        expect($this->calculator->materialAvailabilityPercent([materialSnapshot(MoveState::CONFIRMED, 0, 0)]))->toBe(100.0);
    });

    it('counts an order without components as fully available', function () {
        expect($this->calculator->materialAvailabilityPercent([]))->toBe(100.0);
    });
});

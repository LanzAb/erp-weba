<?php

namespace Weba\ProductionPriority\Enums;

use Filament\Support\Contracts\HasLabel;

enum CriterionSource: string implements HasLabel
{
    case DEADLINE_DAYS = 'deadline_days';
    case URGENCY_LEVEL = 'urgency_level';
    case MATERIAL_AVAILABILITY = 'material_availability';
    case REMAINING_DURATION = 'remaining_duration';
    case PROGRESS = 'progress';
    case ORDER_VALUE = 'order_value';

    public static function options(): array
    {
        return [
            self::DEADLINE_DAYS->value         => __('production-priority::enums/criterion-source.deadline-days'),
            self::URGENCY_LEVEL->value         => __('production-priority::enums/criterion-source.urgency-level'),
            self::MATERIAL_AVAILABILITY->value => __('production-priority::enums/criterion-source.material-availability'),
            self::REMAINING_DURATION->value    => __('production-priority::enums/criterion-source.remaining-duration'),
            self::PROGRESS->value              => __('production-priority::enums/criterion-source.progress'),
            self::ORDER_VALUE->value           => __('production-priority::enums/criterion-source.order-value'),
        ];
    }

    public function getLabel(): string
    {
        return self::options()[$this->value];
    }
}

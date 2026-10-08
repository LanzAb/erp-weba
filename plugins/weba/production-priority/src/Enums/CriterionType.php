<?php

namespace Weba\ProductionPriority\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CriterionType: string implements HasColor, HasLabel
{
    case BENEFIT = 'benefit';
    case COST = 'cost';

    public static function options(): array
    {
        return [
            self::BENEFIT->value => __('production-priority::enums/criterion-type.benefit'),
            self::COST->value    => __('production-priority::enums/criterion-type.cost'),
        ];
    }

    public function getLabel(): string
    {
        return self::options()[$this->value];
    }

    public function getColor(): string
    {
        return match ($this) {
            self::BENEFIT => 'success',
            self::COST    => 'warning',
        };
    }
}

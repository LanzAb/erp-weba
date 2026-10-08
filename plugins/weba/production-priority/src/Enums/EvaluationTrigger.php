<?php

namespace Weba\ProductionPriority\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EvaluationTrigger: string implements HasColor, HasLabel
{
    case MANUAL = 'manual';
    case SCHEDULED = 'scheduled';

    public static function options(): array
    {
        return [
            self::MANUAL->value    => __('production-priority::enums/evaluation-trigger.manual'),
            self::SCHEDULED->value => __('production-priority::enums/evaluation-trigger.scheduled'),
        ];
    }

    public function getLabel(): string
    {
        return self::options()[$this->value];
    }

    public function getColor(): string
    {
        return match ($this) {
            self::MANUAL    => 'primary',
            self::SCHEDULED => 'gray',
        };
    }
}

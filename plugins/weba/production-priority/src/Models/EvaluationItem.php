<?php

namespace Weba\ProductionPriority\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Weba\ProductionPriority\Database\Factories\EvaluationItemFactory;
use Webkul\Manufacturing\Models\Order;

class EvaluationItem extends Model
{
    use HasFactory;

    public const MUTABLE_ATTRIBUTES = ['applied', 'previous_priority', 'updated_at'];

    protected $table = 'production_priority_evaluation_items';

    protected $fillable = [
        'evaluation_id',
        'manufacturing_order_id',
        'raw_values',
        'normalized_values',
        'score',
        'rank',
        'previous_priority',
        'applied',
    ];

    protected $casts = [
        'raw_values'        => 'array',
        'normalized_values' => 'array',
        'score'             => 'decimal:6',
        'rank'              => 'integer',
        'applied'           => 'boolean',
    ];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class, 'evaluation_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'manufacturing_order_id');
    }

    protected static function newFactory(): EvaluationItemFactory
    {
        return EvaluationItemFactory::new();
    }

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function (self $item): void {
            $immutable = array_diff(array_keys($item->getDirty()), self::MUTABLE_ATTRIBUTES);

            if ($immutable !== []) {
                throw new LogicException('An evaluation result can not be changed: '.implode(', ', $immutable).'.');
            }
        });
    }
}

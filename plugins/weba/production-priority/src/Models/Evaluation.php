<?php

namespace Weba\ProductionPriority\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Weba\ProductionPriority\Database\Factories\EvaluationFactory;
use Weba\ProductionPriority\Enums\EvaluationTrigger;
use Webkul\Security\Models\User;
use Webkul\Support\Models\Company;
use Webkul\Support\Traits\BelongsToCompany;

/**
 * A saved SAW run. The criteria, raw values and scores are a snapshot and can
 * not be edited afterwards; only the notes and the "applied" markers change.
 */
class Evaluation extends Model
{
    use BelongsToCompany, HasFactory;

    public const MUTABLE_ATTRIBUTES = ['notes', 'applied_at', 'applied_by', 'applied_top_n', 'updated_at'];

    protected $table = 'production_priority_evaluations';

    protected $fillable = [
        'name',
        'trigger',
        'evaluated_at',
        'criteria_snapshot',
        'warnings',
        'order_count',
        'notes',
        'applied_at',
        'applied_top_n',
        'company_id',
        'evaluated_by',
        'applied_by',
    ];

    protected $casts = [
        'trigger'           => EvaluationTrigger::class,
        'evaluated_at'      => 'datetime',
        'applied_at'        => 'datetime',
        'criteria_snapshot' => 'array',
        'warnings'          => 'array',
        'order_count'       => 'integer',
        'applied_top_n'     => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(EvaluationItem::class, 'evaluation_id')->orderBy('rank');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function applier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function isApplied(): bool
    {
        return $this->applied_at !== null;
    }

    protected static function newFactory(): EvaluationFactory
    {
        return EvaluationFactory::new();
    }

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function (self $evaluation): void {
            $immutable = array_diff(array_keys($evaluation->getDirty()), self::MUTABLE_ATTRIBUTES);

            if ($immutable !== []) {
                throw new LogicException('An evaluation snapshot can not be changed: '.implode(', ', $immutable).'.');
            }
        });
    }
}

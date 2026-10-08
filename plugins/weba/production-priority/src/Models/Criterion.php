<?php

namespace Weba\ProductionPriority\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Weba\ProductionPriority\Database\Factories\CriterionFactory;
use Weba\ProductionPriority\Enums\CriterionSource;
use Weba\ProductionPriority\Enums\CriterionType;
use Weba\ProductionPriority\Support\SawCriterion;
use Webkul\Chatter\Traits\HasLogActivity;
use Webkul\Security\Models\User;
use Webkul\Support\Models\Company;
use Webkul\Support\Traits\BelongsToCompany;

class Criterion extends Model
{
    use BelongsToCompany, HasFactory, HasLogActivity;

    protected $table = 'production_priority_criteria';

    protected $fillable = [
        'code',
        'name',
        'type',
        'source',
        'weight',
        'is_active',
        'sort',
        'company_id',
        'creator_id',
    ];

    protected $casts = [
        'type'      => CriterionType::class,
        'source'    => CriterionSource::class,
        'weight'    => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function getModelTitle(): string
    {
        return __('production-priority::models/criterion.title');
    }

    protected function getLogAttributeLabels(): array
    {
        return [
            'code'      => __('production-priority::models/criterion.log-attributes.code'),
            'name'      => __('production-priority::models/criterion.log-attributes.name'),
            'type'      => __('production-priority::models/criterion.log-attributes.type'),
            'source'    => __('production-priority::models/criterion.log-attributes.source'),
            'weight'    => __('production-priority::models/criterion.log-attributes.weight'),
            'is_active' => __('production-priority::models/criterion.log-attributes.is_active'),
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function toSawCriterion(): SawCriterion
    {
        return new SawCriterion($this->code, $this->type, (float) $this->weight);
    }

    protected static function newFactory(): CriterionFactory
    {
        return CriterionFactory::new();
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $criterion): void {
            $criterion->creator_id ??= Auth::id();
        });
    }
}

<?php

namespace Weba\ProductionPriority\Database\Seeders;

use Illuminate\Database\Seeder;
use Weba\ProductionPriority\Enums\CriterionSource;
use Weba\ProductionPriority\Enums\CriterionType;
use Weba\ProductionPriority\Models\Criterion;
use Webkul\Support\Models\Company;

/**
 * Default criteria and weights are provisional until they are validated
 * with PT. Weba International (interview, questionnaire or AHP).
 */
class CriterionSeeder extends Seeder
{
    /**
     * @return list<array{code: string, name: string, type: CriterionType, source: CriterionSource, weight: float}>
     */
    public static function defaults(): array
    {
        return [
            ['code' => 'C1', 'name' => 'Sisa waktu menuju deadline (hari)', 'type' => CriterionType::COST, 'source' => CriterionSource::DEADLINE_DAYS, 'weight' => 0.30],
            ['code' => 'C2', 'name' => 'Tingkat urgensi pelanggan (1-5)', 'type' => CriterionType::BENEFIT, 'source' => CriterionSource::URGENCY_LEVEL, 'weight' => 0.20],
            ['code' => 'C3', 'name' => 'Ketersediaan bahan baku (%)', 'type' => CriterionType::BENEFIT, 'source' => CriterionSource::MATERIAL_AVAILABILITY, 'weight' => 0.15],
            ['code' => 'C4', 'name' => 'Sisa durasi produksi (jam)', 'type' => CriterionType::COST, 'source' => CriterionSource::REMAINING_DURATION, 'weight' => 0.15],
            ['code' => 'C5', 'name' => 'Progres penyelesaian (%)', 'type' => CriterionType::BENEFIT, 'source' => CriterionSource::PROGRESS, 'weight' => 0.10],
            ['code' => 'C6', 'name' => 'Nilai order (Rp)', 'type' => CriterionType::BENEFIT, 'source' => CriterionSource::ORDER_VALUE, 'weight' => 0.10],
        ];
    }

    public function run(): void
    {
        foreach (Company::query()->pluck('id') as $companyId) {
            foreach (static::defaults() as $position => $criterion) {
                $existing = Criterion::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('code', $criterion['code'])
                    ->first();

                if ($existing) {
                    continue;
                }

                Criterion::withoutGlobalScopes()->create([
                    ...$criterion,
                    'company_id' => $companyId,
                    'is_active'  => true,
                    'sort'       => $position + 1,
                ]);
            }
        }
    }
}

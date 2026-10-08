<?php

namespace Weba\ProductionPriority\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Weba\ProductionPriority\Enums\EvaluationTrigger;
use Weba\ProductionPriority\Services\EvaluationService;
use Webkul\Support\Models\Company;

class RunEvaluationCommand extends Command
{
    protected $signature = 'production-priority:run
        {--company= : Only evaluate this company id (default: every company)}
        {--name= : Name of the evaluation}';

    protected $description = 'Run the SAW production priority evaluation and store the ranking';

    public function handle(EvaluationService $service): int
    {
        $companyIds = $this->option('company')
            ? [(int) $this->option('company')]
            : Company::query()->pluck('id')->all();

        $failed = false;

        foreach ($companyIds as $companyId) {
            try {
                $evaluation = $service->run(
                    companyId: $companyId,
                    trigger: EvaluationTrigger::SCHEDULED,
                    name: $this->option('name') ?: null,
                );
            } catch (InvalidArgumentException $exception) {
                $this->error("Company {$companyId}: {$exception->getMessage()}");

                $failed = true;

                continue;
            }

            if ($evaluation === null) {
                $this->line("Company {$companyId}: no open manufacturing order to rank.");

                continue;
            }

            $this->info("Company {$companyId}: evaluation #{$evaluation->id} saved ({$evaluation->order_count} orders).");
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

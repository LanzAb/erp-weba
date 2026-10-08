<?php

namespace Weba\ProductionPriority\Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CriterionSeeder::class,
        ]);
    }
}

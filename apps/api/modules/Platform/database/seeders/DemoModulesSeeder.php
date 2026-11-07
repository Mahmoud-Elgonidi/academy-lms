<?php

namespace Modules\Platform\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Platform\Models\AcademyModules;

/**
 * The demo academy chose its modules: every optional area on, none hidden
 * (Settings › Academy modules), so its setup step is done. Once: a choice
 * its owner saved stays (DemoCatchUpSeeder runs this).
 */
final class DemoModulesSeeder extends Seeder
{
    public function run(): void
    {
        if (! AcademyModules::query()->exists()) {
            AcademyModules::query()->create(['hidden' => []]);
        }
    }
}

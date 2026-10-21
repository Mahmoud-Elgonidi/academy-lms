<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Curriculum\Database\Seeders\DemoQuranTextsSeeder;
use Modules\Learning\Database\Seeders\DemoHifzSeeder;
use Modules\Learning\Database\Seeders\DemoMistakesSeeder;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Database\Seeders\DemoAiAreaSeeder;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * What later versions of the demo add to a demo that is already there: a
 * server deploys with DEMO_DATA=0 once its demo exists, so deploy.sh runs
 * this on every deploy. Once the Quran's text is on the server, the Juz'
 * Amma lessons read their surahs, each Quran learner's last lesson has a few
 * marked words, and the reports already handed in make their hifz maps; and
 * the demo's plan takes the AI helpers' area. Nothing without the demo
 * academy (an academy's own server), and each step only once.
 */
final class DemoCatchUpSeeder extends Seeder
{
    public function run(TenantDirectory $directory, TenantContext $tenants): void
    {
        $demo = $directory->bySlug('demo');
        if ($demo === null) {
            $this->command?->info('No demo academy here: nothing to bring up to date.');

            return;
        }
        $counts = $tenants->run($demo, function (): array {
            $this->call([DemoQuranTextsSeeder::class, DemoMistakesSeeder::class, DemoHifzSeeder::class]);

            return [
                DB::table('curriculum_nodes')->whereNotNull('text_source')->count(),
                DB::table('hifz_pages')->distinct()->count('student_id'),
                DB::table('recitation_mistakes')->count(),
            ];
        });
        $this->call(DemoAiAreaSeeder::class);
        $this->command?->info(vsprintf('Demo: %d lessons with their text, %d learners with a hifz map, %d marked words.', $counts));
    }
}

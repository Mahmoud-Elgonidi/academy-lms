<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Database\Seeders\DemoRegionCountriesSeeder;
use Modules\Crm\Database\Seeders\DemoSelfStartSeeder;
use Modules\Crm\Database\Seeders\DemoTrialReportSeeder;
use Modules\People\Database\Seeders\DemoTeacherCardsSeeder;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Database\Seeders\DemoAiAreaSeeder;
use Modules\Platform\Database\Seeders\DemoQuranAreaSeeder;
use Modules\Quran\Database\Seeders\DemoHifzSeeder;
use Modules\Quran\Database\Seeders\DemoMistakesSeeder;
use Modules\Quran\Database\Seeders\DemoQuranPlansSeeder;
use Modules\Quran\Database\Seeders\DemoQuranSeeder;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\Site\Database\Seeders\DemoSiteSeeder;

/**
 * What later versions of the demo add to a demo that is already there: a
 * server deploys with DEMO_DATA=0 once its demo exists, so deploy.sh runs
 * this on every deploy. Once the Quran's text is on the server, the Juz'
 * Amma lessons read their surahs, each Quran learner's last lesson has a few
 * marked words, and the reports already handed in make their hifz maps; the
 * demo's plan takes the AI helpers' and the Quran's areas; and its public page is on, with
 * three teachers' cards and its price regions naming their countries; and
 * group lessons (DemoGroupsSeeder: teachers who take groups, group plans and
 * pay, a running group of adults, a children's group and one forming); and, once
 * the Quran's text is there, its Quran program (DemoQuranPlansSeeder: exams and
 * certificates on, two ready tracks, its Quran learners on plans, Yousef's last
 * lessons recorded, a certificate and an exam waiting to be booked); and
 * after the free trial (DemoTrialReportSeeder: the offer and the second
 * trial on, Jana's trial a report her mother can read, with an open offer);
 * and the family starting a course on its own (DemoSelfStartSeeder: self-serve
 * on, Jana's mother signs in as jana.mother@demo.test to start from the report).
 * Nothing without the demo academy (an academy's own server), and each step
 * only once.
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
        // The Quran's area first: what follows writes the demo's Quran (its subject, its lessons' passages).
        $this->call(DemoQuranAreaSeeder::class);
        $counts = $tenants->run($demo, function (): array {
            $this->call([DemoQuranSeeder::class, DemoMistakesSeeder::class, DemoHifzSeeder::class, DemoRegionCountriesSeeder::class, DemoTeacherCardsSeeder::class, DemoSiteSeeder::class, DemoGroupsSeeder::class, DemoQuranPlansSeeder::class, DemoTrialReportSeeder::class, DemoSelfStartSeeder::class]);

            return [
                DB::table('quran_lesson_passages')->count(),
                DB::table('hifz_pages')->distinct()->count('student_id'),
                DB::table('recitation_mistakes')->count(),
                DB::table('lesson_groups')->where('status', '!=', 'ended')->count(),
                DB::table('quran_plans')->where('status', 'active')->count(),
                DB::table('quran_certificates')->count(),
                DB::table('lead_trials')->whereNotNull('offer_coupon_id')->count(),
            ];
        });
        $this->call(DemoAiAreaSeeder::class);
        $this->command?->info(vsprintf('Demo: %d lessons with their passage of the Quran, %d learners with a hifz map, %d marked words, %d groups, %d Quran plans, %d Quran certificates, %d trial offers.', $counts));
    }
}

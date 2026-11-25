<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Database\Seeders\DemoAutoRenewSeeder;
use Modules\Billing\Database\Seeders\DemoCollectionSeeder;
use Modules\Billing\Database\Seeders\DemoRegionCountriesSeeder;
use Modules\Crm\Actions\FillLearnerCountries;
use Modules\Crm\Database\Seeders\DemoSelfStartSeeder;
use Modules\Crm\Database\Seeders\DemoTrialReportSeeder;
use Modules\People\Database\Seeders\DemoTeacherCardsSeeder;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Database\Seeders\DemoAiAreaSeeder;
use Modules\Platform\Database\Seeders\DemoModulesSeeder;
use Modules\Platform\Database\Seeders\DemoRechargeSeeder;
use Modules\Platform\Database\Seeders\DemoUsageSeeder;
use Modules\Scheduling\Database\Seeders\DemoOpenHoursSeeder;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\Site\Database\Seeders\DemoSiteSeeder;

/**
 * What later versions of the demo add to a demo that is already there: a
 * server deploys with DEMO_DATA=0 once its demo exists, so deploy.sh runs
 * this on every deploy. The demo's plan takes the AI helpers' area; its
 * public page is on, with three teachers' cards and its price regions naming
 * their countries; and group lessons (DemoGroupsSeeder: teachers who take
 * groups, group plans and pay, a running group of adults, a children's group
 * and one forming); and after the free trial (DemoTrialReportSeeder: the offer and the second
 * trial on, Jana's trial a report her mother can read, with an open offer);
 * and the family starting a course on its own (DemoSelfStartSeeder: self-serve
 * on, Jana's mother signs in as jana.mother@demo.test to start from the report);
 * and a family mid-way through the collection timeline (DemoCollectionSeeder:
 * Hala's family invoice due tomorrow); and a family on automatic renewal
 * (DemoAutoRenewSeeder: a demo card saved for the family whose renewal is nearest);
 * and the CRM's learners and parents with their lead's country, where they had
 * none, so a family is priced where it lives (FillLearnerCountries); and its
 * modules chosen, every optional one on (DemoModulesSeeder); and its usage
 * ledger, last month and this month so far with a pack bought (DemoUsageSeeder),
 * and those packs' purchases with the platform's receipts (DemoRechargeSeeder);
 * and a training office for adult professionals beside it (DemoTrainingSeeder:
 * academy "training", no guardians and no children, training.owner@demo.test).
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
        $counts = $tenants->run($demo, function (): array {
            $this->call([DemoModulesSeeder::class, DemoRegionCountriesSeeder::class, DemoTeacherCardsSeeder::class, DemoSiteSeeder::class, DemoGroupsSeeder::class, DemoTrialReportSeeder::class, DemoSelfStartSeeder::class, DemoOpenHoursSeeder::class, DemoCollectionSeeder::class, DemoAutoRenewSeeder::class]);
            // The CRM's learners and parents take their lead's country where they have none (Jana's mother pays in Egypt's region).
            app(FillLearnerCountries::class)->handle();

            return [
                DB::table('lesson_groups')->where('status', '!=', 'ended')->count(),
                DB::table('lead_trials')->whereNotNull('offer_coupon_id')->count(),
            ];
        });
        // The training office for adult professionals beside it (once: nothing when it is there).
        $this->call(DemoTrainingSeeder::class);
        $this->call(DemoAiAreaSeeder::class);
        // The usage ledger (video minutes, AI credits) with last month, this month so far and a pack bought.
        $this->call(DemoUsageSeeder::class);
        // Its purchases of those packs, each with the platform's numbered receipt.
        $this->call(DemoRechargeSeeder::class);
        $this->command?->info(vsprintf('Demo: %d groups, %d trial offers.', $counts));
    }
}

<?php

namespace Modules\Scheduling\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Actions\Policy\SavePolicySettings;
use Modules\Scheduling\Domain\Policy\PolicySettings;

/**
 * The demo's cancellation, makeup and absence policy, looked at and kept
 * by its owner before the first lesson (version 1 is hers, the usual
 * numbers): a reviewed policy is one of "Start here"'s essentials. Runs
 * first inside the demo academy, so every lesson's decision reads it.
 */
final class DemoPolicySeeder extends Seeder
{
    public function run(SavePolicySettings $policy): void
    {
        $owner = DB::table('users')->where('email', 'owner@demo.test')->value('id');
        if ($owner !== null) {
            $policy->handle(PolicySettings::defaults()->toArray(), $owner);
        }
    }
}

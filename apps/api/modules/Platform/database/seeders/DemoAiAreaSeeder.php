<?php

namespace Modules\Platform\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Models\SaasSubscription;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Plans\Feature;

/**
 * The demo academy's plan takes the AI helpers' area (its owner sees
 * Settings › AI helpers; each helper stays off until switched on there, and
 * works once the platform has its keys). Once: a demo whose plan has the area,
 * or had it taken away, stays as it is.
 */
final class DemoAiAreaSeeder extends Seeder
{
    public function run(ChangeAcademyPlan $change, TenantDirectory $academies): void
    {
        $demo = $academies->bySlug('demo');
        $current = $demo === null ? null : SaasSubscription::currentFor($demo->id);
        $ai = Feature::Ai->value;
        if ($current === null || in_array($ai, $current->features_on, true) || in_array($ai, $current->features_off, true)
            || in_array($ai, (array) $current->plan?->features, true)) {
            return;
        }
        AuditActor::use(new AuditActor('system', null, null));
        $change->handle($demo->id, $current->plan_id, 'الديمو: مساحة المساعدات الآلية', $current->limits, [...$current->features_on, $ai], $current->features_off);
        AuditActor::forget();
    }
}

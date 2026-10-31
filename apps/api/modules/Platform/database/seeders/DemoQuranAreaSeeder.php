<?php

namespace Modules\Platform\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Models\SaasSubscription;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Plans\Feature;

/**
 * The demo academy's plan has the Quran's area (its hifz maps, mushaf and
 * recitation checks stay where they were when the Quran became an area a
 * plan may leave out). Once: a demo whose plan has the area, or had it
 * taken away on purpose, stays as it is.
 */
final class DemoQuranAreaSeeder extends Seeder
{
    public function run(ChangeAcademyPlan $change, TenantDirectory $academies): void
    {
        $demo = $academies->bySlug('demo');
        $current = $demo === null ? null : SaasSubscription::currentFor($demo->id);
        $quran = Feature::Quran->value;
        if ($current === null || in_array($quran, $current->features_on, true) || in_array($quran, $current->features_off, true)
            || in_array($quran, (array) $current->plan?->features, true)) {
            return;
        }
        AuditActor::use(new AuditActor('system', null, null));
        $change->handle($demo->id, $current->plan_id, 'العرض التجريبي: مساحة القرآن', $current->limits, [...$current->features_on, $quran], $current->features_off);
        AuditActor::forget();
    }
}

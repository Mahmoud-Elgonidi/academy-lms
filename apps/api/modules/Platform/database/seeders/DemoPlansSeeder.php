<?php

namespace Modules\Platform\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Plans\MeasureUsage;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Plans\Feature;

/**
 * The demo's plans: the demo academy on "Pro" with its own teacher limit
 * (agreed with the platform team; nine of its ten places taken, so its
 * owner sees the warning and an import of two teachers stops at the
 * second) and the AI helpers' area added, and every academy counted for the
 * console.
 */
final class DemoPlansSeeder extends Seeder
{
    public function run(ChangeAcademyPlan $change, MeasureUsage $measure, TenantDirectory $academies, string $adminId, string $adminName): void
    {
        $demo = $academies->bySlug('demo');
        $pro = SaasPlan::query()->where('code', 'pro')->first();
        if ($demo !== null && $pro !== null) {
            AuditActor::use(new AuditActor('platform', $adminId, $adminName));
            $change->handle($demo->id, $pro->id, 'اتفاق على 10 مدرسين بس لحد تجديد العقد في يناير', ['teachers' => 10], [Feature::Ai->value]);
            AuditActor::forget();
        }
        $measure->all();
    }
}

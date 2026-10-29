<?php

namespace Modules\Identity\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\Impersonation;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Past platform support sign-ins in the demo academy: the platform team
 * opened the guardian's account to see a missing invoice (they left after
 * 9 minutes) and the teacher's for a schedule question (its time ran out),
 * each with its lines in the activity log.
 */
final class DemoSupportSeeder extends Seeder
{
    public function run(TenantContext $tenants, string $adminId, string $adminName): void
    {
        $academyId = $tenants->require()->id;
        $now = CarbonImmutable::now();
        $visits = [
            ['guardian@demo.test', 6, 15, 'يقول ولي الأمر إن فاتورة الشهر لا تظهر لديه في التطبيق، ونريد أن نرى ما يراه.', 'exit', 9],
            ['teacher@demo.test', 2, 30, 'لا يرى المعلّم حصة يوم الخميس في جدوله، بينما تراها الإدارة. نراجع ما يظهر لديه.', 'expired', 30],
        ];

        foreach ($visits as [$email, $daysAgo, $minutes, $reason, $how, $stayed]) {
            $user = User::query()->where('email', $email)->first();
            if ($user === null) {
                continue;
            }
            $start = $now->subDays($daysAgo)->setTime(11, 20);
            $impersonation = Impersonation::query()->create([
                'user_id' => $user->id,
                'admin_id' => $adminId,
                'admin_name' => $adminName,
                'reason' => $reason,
                'minutes' => $minutes,
                'redeem_by' => $start->addMinutes(2),
                'redeemed_at' => $start->addSeconds(40),
                'expires_at' => $start->addSeconds(40)->addMinutes($minutes),
                'ended_at' => $start->addSeconds(40)->addMinutes($stayed),
                'ended_reason' => $how,
            ]);
            $impersonation->forceFill(['created_at' => $start, 'updated_at' => $start->addMinutes($stayed)])->save();

            $actor = new AuditActor('platform', $adminId, $adminName, $user->id, $impersonation->id);
            DB::table('audit_logs')->insert([
                ['created_at' => $start] + AuditTrail::row($academyId, $actor, 'impersonation.started', 'user', $user->id, ['user' => $user->name, 'minutes' => $minutes, 'reason' => $reason]),
                ['created_at' => $start->addSeconds(40)->addMinutes($stayed)] + AuditTrail::row($academyId, $actor, 'impersonation.ended', 'user', $user->id, ['user' => $user->name, 'how' => $how]),
            ]);
        }
    }
}

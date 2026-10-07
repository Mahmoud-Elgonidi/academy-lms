<?php

namespace Modules\Platform\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Actions\SetAcademyStatus;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\PlatformAdmin;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * The demo platform team: admin@platform.test / password, with a fixed
 * authenticator secret so `php artisan platform:code admin@platform.test`
 * prints the code, a few past console sign-ins, and the second demo academy
 * suspended with a reason.
 */
final class DemoPlatformSeeder extends Seeder
{
    /** Base32; add it to any authenticator app to sign in to the demo console from a phone. */
    public const SECRET = 'LMSDEMOPLATFORMCONSOLE2FASECRET2';

    public function run(SetAcademyStatus $status): void
    {
        $admin = PlatformAdmin::query()->create([
            'name' => 'سلمى من فريق المنصة',
            'email' => 'admin@platform.test',
            'password' => 'password',
            'locale' => 'ar',
            'timezone' => 'Africa/Cairo',
        ]);
        $admin->saveAppAuthenticationSecret(self::SECRET);
        $actor = new AuditActor('platform', $admin->id, $admin->name);

        $now = CarbonImmutable::now();
        foreach ([9, 4, 1] as $daysAgo) {
            DB::connection('pgsql_platform')->table('audit_logs')->insert(
                ['created_at' => $now->subDays($daysAgo)->setTime(9, 12 + $daysAgo)] + AuditTrail::row(null, $actor, 'platform.signed_in', 'platform_admin', $admin->id, []),
            );
        }
        $admin->forceFill(['last_login_at' => $now->subDay()->setTime(9, 13)])->save();

        $rawda = Academy::query()->where('slug', 'rawda')->first();
        if ($rawda !== null) {
            AuditActor::use($actor);
            $status->suspend($rawda->id, 'الاشتراك متأخر شهرين، والمالك طلب إيقاف مؤقت لحد ما يسدد.');
            AuditActor::forget();
        }
    }
}

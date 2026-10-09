<?php

namespace Modules\Privacy\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Privacy\Actions\BuildDataExport;
use Modules\Privacy\Actions\RefuseErasure;
use Modules\Privacy\Actions\RequestDataExport;
use Modules\Privacy\Actions\RequestErasure;
use Modules\Privacy\Actions\SavePrivacySettings;
use Modules\Privacy\Actions\SweepDataRequests;

/**
 * People's requests about their data in the demo, made through the Actions
 * at their own times: the academy's details on its privacy page; Rana's
 * father asks for Rana's account to be erased (she finished; nothing holds
 * it back, ready to approve); Omar's father asks to erase his own account
 * (an invoice is still due); Hamza's father asked a month ago and was
 * refused (a course was running then); Maryam's father has a copy of her
 * data to download; a teacher's copy from last month has expired.
 */
final class DemoPrivacySeeder extends Seeder
{
    public function run(RequestDataExport $export, RequestErasure $erasure, RefuseErasure $refuse, BuildDataExport $build, SavePrivacySettings $settings, SweepDataRequests $sweep): void
    {
        $id = fn (string $email) => DB::table('users')->where('email', $email)->value('id');
        $owner = $id('owner@demo.test');
        if ($owner === null) {
            return;
        }
        $now = CarbonImmutable::now();
        $at = function (CarbonImmutable $when, callable $do) {
            CarbonImmutable::setTestNow($when);
            try {
                return $do();
            } finally {
                CarbonImmutable::setTestNow();
            }
        };
        // The office's steps are the owner's.
        Auth::onceUsingId($owner);

        $settings->handle([
            'legal_name' => 'مؤسسة النور للتعليم عن بعد',
            'contact_email' => 'privacy@demo.test',
            'address' => 'الرياض، المملكة العربية السعودية',
            'privacy_extra' => 'بنستخدم واتساب في رسايل الحصص بس، ومابنبعتش عروض من غير ما توافق.',
        ], $owner);

        // Rana finished: her father asks for her account to go.
        if (($rana = $id('rana@demo.test')) && ($ranasFather = $id('rana.guardian@demo.test'))) {
            $at($now->subDays(3)->setTime(20, 15), fn () => $erasure->handle($rana, $ranasFather, 'family', 'رنا خلّصت المستوى ومش هتكمل، ياريت تمسحوا حسابها.'));
        }
        // Omar's father wants his own account gone, with an invoice still due.
        if ($omarsFather = $id('omar.guardian@demo.test')) {
            $at($now->subDays(6)->setTime(9, 40), fn () => $erasure->handle($omarsFather, $omarsFather, 'family'));
        }
        // Hamza's father asked last month while Hamza's course was running: refused, with the reason he read.
        if (($hamza = $id('hamza@demo.test')) && ($hamzasFather = $id('hamza.guardian@demo.test'))) {
            $asked = $at($now->subDays(34)->setTime(18, 0), fn () => $erasure->handle($hamza, $hamzasFather, 'family', 'هنوقف شوية.'));
            $at($now->subDays(33)->setTime(10, 30), fn () => $refuse->handle($asked, $owner,
                'حمزة لسه عنده كورس شغال وحصص محجوزة الشهر ده. لو حابب توقف، كلمنا نوقف الكورس الأول وبعدها نمسح الحساب.'));
        }
        // Maryam's father downloads a copy of her data.
        if (($maryam = $id('maryam@demo.test')) && ($maryamsFather = $id('maryam.guardian@demo.test'))) {
            $copy = $at($now->subHours(5), fn () => $export->handle($maryam, $maryamsFather, 'family'));
            $at($now->subHours(5)->addMinutes(2), fn () => $build->handle($copy));
        }
        // A teacher's copy from last month, past its week.
        if ($teacher = $id('teacher@demo.test')) {
            $old = $at($now->subDays(20), fn () => $export->handle($teacher, $teacher, 'teacher'));
            $at($now->subDays(20)->addMinutes(2), fn () => $build->handle($old));
            $sweep->handle();
        }

        Auth::forgetUser();
    }
}

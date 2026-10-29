<?php

namespace Modules\Announcements\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Announcements\Actions\SendAnnouncement;
use Modules\Announcements\Domain\Audience;

/**
 * Two announcements in the demo, sent by the owner through the Action at
 * their own times: the Eid holiday to every family a few days ago (with a
 * phone notification), and the new report questions to the teachers
 * yesterday (by email). Each person has them in their notifications.
 */
final class DemoAnnouncementsSeeder extends Seeder
{
    public function run(SendAnnouncement $send): void
    {
        $owner = DB::table('users')->where('email', 'owner@demo.test')->value('id');
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

        Auth::onceUsingId($owner);
        try {
            $at($now->subDays(4)->setTime(9, 30), fn () => $send->handle($owner, 'إجازة عيد الفطر',
                "الأكاديمية في إجازة من أول أيام العيد حتى ثالث يوم.\nتعود الحصص إلى مواعيدها مباشرة بعدها، وتعود دقائق الحصص التي تقع في الإجازة إلى رصيدكم.\nكل عام وأنتم بخير!",
                Audience::of(Audience::FAMILIES), ['push']));
            $at($now->subDay()->setTime(17, 0), fn () => $send->handle($owner, 'أسئلة جديدة في تقرير الحصة',
                "أضفنا سؤالين في آخر تقرير الحصة عن التلاوة والتجويد.\nنرجو أن تجيبوا عنهما من اليوم، وسترى الأسر الإجابات في تقرير ابنها.",
                Audience::of(Audience::TEACHERS), ['email']));
        } finally {
            Auth::forgetGuards();
        }
    }
}

<?php

namespace Modules\Safeguarding\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Safeguarding\Actions\AcknowledgeConcern;
use Modules\Safeguarding\Actions\AddConcernNote;
use Modules\Safeguarding\Actions\CloseConcern;
use Modules\Safeguarding\Actions\ReportConcern;

/**
 * Concerns about children's safety in the demo, through the Actions at
 * their own times (the owner is the academy's safeguarding lead): Maryam's
 * father says someone asked her to carry on over WhatsApp (taken, a note
 * written); Yousef pressed "Tell a grown-up" half an hour ago without words
 * (new); Nour worries about a student of hers and marked it urgent (taken);
 * and one from last month is closed with a reply Zain's father read.
 */
final class DemoSafeguardingSeeder extends Seeder
{
    public function run(ReportConcern $report, AcknowledgeConcern $take, AddConcernNote $note, CloseConcern $close): void
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

        // Zain's father, last month: closed, with the reply he read.
        if (($zain = $id('zain@demo.test')) && ($zainsFather = $id('zain.guardian@demo.test'))) {
            $old = $at($now->subDays(26)->setTime(19, 10), fn () => $report->handle($zainsFather, 'family', $zain, 'content',
                'في آخر الحصة فُتح فيديو يوتيوب على الشاشة المشتركة وظهر إعلان غير مناسب لطفل.'));
            $at($now->subDays(26)->setTime(19, 40), fn () => $take->handle($old->id, $owner));
            $at($now->subDays(25)->setTime(11, 0), fn () => $note->handle($old->id, 'تحدثت مع أ. خالد: ظهر الإعلان من فيديو ليس من مكتبة الأكاديمية. اتفقنا على أن تُعرض الفيديوهات من ملفات الدرس فقط.', $owner));
            $at($now->subDays(24)->setTime(10, 15), fn () => $close->handle($old->id, 'resolved',
                'شكرًا لأنك أخبرتنا. تحدثنا مع المعلّم، ومن الآن يُعرض أي فيديو في الحصة من ملفات الدرس فقط.', 'نُبّه المعلّم كتابةً؛ الفيديوهات من مكتبة الأكاديمية فقط.', $owner));
        }

        // Maryam's father, two days ago, about one of her lessons.
        if (($maryam = $id('maryam@demo.test')) && ($maryamsFather = $id('maryam.guardian@demo.test'))) {
            $lesson = DB::table('sessions as s')->join('session_participants as p', fn ($j) => $j->on('p.academy_id', '=', 's.academy_id')->on('p.session_id', '=', 's.id'))
                ->where('p.user_id', $maryam)->where('p.role', 'student')->where('s.starts_at', '<', $now->subDays(2))->where('s.starts_at', '>', $now->subDays(20))
                ->orderByDesc('s.starts_at')->value('s.id');
            $concern = $at($now->subDays(2)->setTime(21, 30), fn () => $report->handle($maryamsFather, 'family', $maryam, 'outside_contact',
                'أخبرتني مريم أنه في آخر الحصة طُلب منها رقم الواتساب الخاص بها "لإكمال المراجعة خارج المنصة". لم تعطه لهم.', false, $lesson));
            $at($now->subDays(2)->setTime(21, 52), fn () => $take->handle($concern->id, $owner));
            $at($now->subDay()->setTime(10, 20), fn () => $note->handle($concern->id, 'تحدثت مع الأب: مريم بخير ولم ترسل شيئًا. سأراجع تسجيل الحصة وأتحدث مع المعلّمة اليوم.', $owner));
        }

        // Nour, this morning, about a student of hers: urgent.
        if (($nour = $id('nour@demo.test')) && ($yousef = $id('yousef@demo.test'))) {
            $hers = $at($now->subHours(5), fn () => $report->handle($nour, 'teacher', $yousef, 'wellbeing',
                'كان يوسف متعبًا جدًا وقال إنه لم ينم وإنه لا يوجد أحد في البيت منذ الأمس. لم أسأله أكثر من ذلك.', true));
            $at($now->subHours(4)->subMinutes(35), fn () => $take->handle($hers->id, $owner));
        }

        // Yousef himself pressed "Tell a grown-up", half an hour ago, without words.
        if ($yousef = $id('yousef@demo.test')) {
            $at($now->subMinutes(30), fn () => $report->handle($yousef, 'family', $yousef, 'bullying', null));
        }
    }
}
